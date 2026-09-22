# BR-COM-02 — Approval / State-Machine Engine (SuiteCRM Advanced Workflow)

**Modules:** ksf_FA_Common (host), ksf_FA_HRM, ksf_FA_CRM, ksf_FA_Calendar,
any module with records that flow through human/automated approval
**Status:** PENDING (design ratified in this BR)
**Built on:** BR-COM-01 (workflow step-table engine) + FR-COM-01-006
(`get_dto`/`get_dto_list` system hooks) + BR-007 (event-close hook protocol)

## Business Need

BR-COM-01 gives us *automatic reactions* (criteria → action → chain). But HRM
leave, CRM quotes, recruitment, expense reports, and offboarding all need the
other half of SuiteCRM's Advanced Workflow / Process Author model: a **record
that sits in a state**, moves through **legal transitions**, where each
transition may run **actions (function calls)** and may require a **human
decision** or a **time wait**. Sandahlian CRUD-with-a-status-column cannot
express "submit → 3-level approve → notify → accrue", nor can it enforce
"cannot approve with zero balance".

SuiteCRM's Process Author story (the part the user referenced): a **Process**
definition, **Activities** that hold per-record **runtime state**, directed
**Transitions** between activities (each transition can carry actions /
function calls / wait conditions), **start conditions** that fire the process,
and a **UI for designing the flow** (nodes + edges) that serializes back to the
definition.

## Business Requirement

The business requires:

1. **State machine core** — per record instance: `current_state` persisted
   (own table; record itself untouched), a **states registry** (which states
   exist for a Process), and a **transitions registry** (legal
   `from → to` edges plus guards).
2. **Activities-at-read-time** — a DTO in-flight always exposes derived info:
   `state`, `allowed_transitions[to => label]`, `history` (state changes with
   actor/timestamp). Supplied via `after_retrieve` step on BR-COM-01; no
   module code needed beyond steps.
3. **Transitions carry actions** — an edge is: `from → to`, optional
   **guard** (criteria over DTO, reuses BR-COM-01 criteria DSL), optional
   **pre-action set** (function calls / CalcRegistry resolvers / DO verbs),
   optional **post-action set**, and optional **wait condition** (time-based,
   falls through to BR-COM-04 scheduler for wakeup).
4. **Human or automated trigger** — a transition may be:
   - user-initiated (designer-flagged; the record page renders the allowed
     transition buttons via `allowed_transitions`),
   - auto-fire (evaluated on `after_save` by BR-COM-01 steps, e.g. "reject &
     reset to draft when approver declines"),
   - time-gated (wait until a deadline, then auto-fire or prompt).
5. **Persistence & audit** — every transition writes `(recordType, id,
   from, to, actor, at, reason)`; queriable via `get_dto_list` on the state
   history DTO; immutable (append-only), mirroring BR-007's append-only
   worked-window discipline.
6. **Consistency guards** — a transition is refused if: guard fails,
   `from != current_state`, the target is same-state (no-op edges rejected
   in the designer), or the actor lacks the transition's required role
   (checked against FA security areas; no new auth subsystem).
7. **Designer UI** — suiteCRM Process-Author-like flow builder: nodes =
   states, edges = transitions (with guard/pause/Actions editors), start
   conditions reusing the step-table criteria editor, publishing a versioned
   JSON definition to the registry (authorable as data, BR-COM-01 req 8).
8. **Fault tolerance + loop safety** — BR-COM-01 guard rails apply to any
   actions a transition runs; a state machine must be **acyclic** at design
   time (cycle detection in the designer), even though processes may be
   re-entrant at runtime via gates (submitted → rejected → resubmitted).

## Scope

- In scope: state-machine core, transitions registry, designer UI (v1:
  JSON authoring + read-only visual), audit history, guards, time-gating
  lease to BR-COM-04.
- Out of scope: notifications/feed (BR-COM-03), scheduled wakeups (BR-COM-04),
  per-role matrix UI (uses FA security areas), email templates.

## Design — Processes, Activities, Transitions

### Registry (data, serializable)

```php
// ksf_fa_common/src/Workflow/ProcessDefinition.php
$leaveProcess = [
  'name'       => 'leave_request.approval',          // keyed by record type
  'record'     => 'leave_request',
  'record_prefix' => 'ksf_FA_HRM',                   // existing registerWorkflowType
  'start'      => ['on' => 'after_save',             // SuiteCRM start condition
                   'criteria' => [['status','eq','submitted']]],
  'states'     => ['draft','submitted','pending','scheduled','approved',
                   'rejected','rejected_exhausted','cancelled'],
  'initial'    => 'draft',
  'transitions'=> [
    ['from' => 'draft', 'to' => 'submitted',
     'trigger' => 'user', 'label' => 'Submit',
     'pre'  => [['call','hrm.leave.validate_fields']]],
    ['from' => 'submitted', 'to' => 'pending',
     'trigger' => 'auto',
     'pre'  => [['resolver','hrm.approval_chain','nextStep'],
                ['set','current_approver','=','result.person_id']],
     'post' => [['create','ApprovalTask','copy'=>['request_id','current_approver']]]],
    ['from' => 'pending', 'to' => 'approved',
     'trigger' => 'user', 'label' => 'Approve',
     'guard' => [['result.balance_remaining','gte','result.hours_requested']],
     'pre'  => [['call','hrm.leave.check_balance']],
     'post' => [['broadcast','leave_approved',['dto']]]],
    ['from' => 'pending', 'to' => 'rejected',
     'trigger' => 'user', 'label' => 'Reject',
     // designer marks reason as REQUIRED prompt field; enforced at transition
     'prompt' => [['reason','=','required']]],
    ['from' => 'pending', 'to' => 'rejected_exhausted',
     'trigger' => 'auto',
     'guard' => [['result.balance_remaining','lt','result.hours_requested']],
     'post' => [['set','reason','=','no balance']]],
    ['from' => 'approved', 'to' => 'scheduled',
     'trigger' => 'auto', 'on' => 'after_save',
     'pre'  => [['call','hrm.timesheet.create_work_window']]],
  ],
  'history'    => true,
];
```

### Runtime (engine walk)

1. **Trigger**: BR-COM-01 step for `(record, event)` consults start conditions;
   matching instance → engine loads/creates its state row.
2. **Read**: `after_retrieve` step attaches `state`, `allowed_transitions`
   (name + label) and `history` to the DTO.
3. **Transition**: `engine->transition(dto, to, actor, reason)` →
   - guard evaluation (criteria DSL; `result.*` from pre-action resolver
     outputs),
   - `pre` action list runs (CalcRegistry + DO verbs),
   - handler persists `from → to` (INSERT only), updates current_state,
   - `post` action list runs (may `create` DTO → fires its own BR-COM-01
     chain, or `broadcast`, e.g. `leave_approved`),
   - history row written with actor/reason/at.
4. **Atomicity**: state row + history + post-actions run inside one FA
   transaction (`begin_transaction`/`commit_transaction` around `call_transaction`
   wrapper); failure rolls the state back to `from`.
5. **Time-gating**: an edge with `wait` registers a wakeup task (BR-COM-04)
   — engine state untouched until scheduler fires the transition.

### The workflow table (designer-facing, per the user's SuiteCRM inspiration)

| # | Activity/State (node) | Start/On (trigger criteria — IF on DTO) | Transition (edge `from→to`) | Guard (criteria; may use `result.*`) | Pre-actions THEN (CalcRegistry / function calls) | Post-actions DO (set/create/call/broadcast) | Trigger (user/auto/time) | Required role/access |
|---|----------------------|-----------------------------------------|-----------------------------|--------------------------------------|---------------------------------------------------|----------------------------------------------|------------------------|----------------------|
| 1 | draft | — | draft→submitted | (none) | `hrm.leave.validate_fields` | `set status=submitted`; re-fire `after_save` | user | SA_LEAVE_* |
| 2 | submitted | start: `status=submitted` | submitted→pending | (none) | `hrm.approval_chain.nextStep` | `set current_approver`; `create ApprovalTask` | auto | — |
| 3 | pending | — | pending→approved | `balance_remaining >= hours_requested` | `hrm.leave.check_balance` | `broadcast leave_approved` | user | SA_LEAVE_APPROVE |
| 4 | pending | — | pending→rejected | (none) | (none) | `set reason=@prompt` | user | SA_LEAVE_APPROVE |
| 5 | pending | — | pending→rejected_exhausted | `balance_remaining < hours_requested` | (none) | `set reason='no balance'` | auto | — |
| 6 | approved | — | approved→scheduled | (none) | `hrm.timesheet.create_work_window` | — | auto | — |

**This IS the SuiteCRM Process Author table rendered as our step-table.**
Nodes = states; edges = transitions; the criteria/guard/then/do columns reuse
BR-COM-01 verbatim; the designer UI just edits this table as JSON/forms.

### Designer UI (FR-COM-02-007)

- **v1 minimal**: JSON editor + validation (acyclic check, state names unique,
  transition targets exist, guard predicates well-formed) + read-only node/edge
  graph (dot/vis) + publish → registry. Same dev tooling as `get_dto` schema
  browser (FR-COM-01-007).
- Signed (versioned) definitions: `definition_id + version`; published
  definitions are append-only (a running process pins its definition version;
  new instances pick the latest).
- Exposes, per activated module, the DTO fields via `get_dto` schema for guard
  and DO authoring — the "then part can create a new DTO for any activated
  module, data fillable" requirement.

## Supporting FRs

- FR-COM-02-001 states registry + per-record current_state persistence
  (append-only history table `0_ksf_wf_state` + `0_ksf_wf_history`)
- FR-COM-02-002 transitions registry (from/to/guard/pre/post/trigger/wait,
  serialized JSON → registry) with design-time acyclicity
- FR-COM-02-003 engine walk (trigger, read-attachment, transition, atomicity
  via FA transaction, time-gating lease to BR-COM-04)
- FR-COM-02-004 history + audit (immutable rows, actor/reason/at, get_dto_list
  exposed)
- FR-COM-02-005 record-page integration (renders `allowed_transitions` as
  buttons via `after_retrieve`) + role gating via FA security areas
- FR-COM-02-006 designer JSON editor + validation + versioning + publish
- FR-COM-02-007 e2e: leave submit→approve→scheduled against live FA DB,
  mirrors `e2e_hrm_event_windows.php`

## Acceptance Criteria (UAT)

1. Unit: the leave process above is fully exercised — guards refuse approve
   with exhausted balance (→ rejected_exhausted), pre-actions set
   current_approver, post `create ApprovalTask` fires its own after_save chain.
2. Unit: illegal transition (`draft→approved` bypass) throws/logs and state
   unchanged; same-state and self edges rejected by the validator.
3. Atomicity: a failing post-action (call throws) rolls state back to `from`
   and writes a `failed` history entry; caller loop continues.
4. Designer: a cyclic definition is refused on save; a valid one publishes a
   versioned JSON that round-trips to identical behaviour in tests.
5. e2e in FA php7.4 container: seeded leave_request transitions draft→
   submitted (user) → pending (auto) → approved (user) → scheduled (auto);
   asserts state row, 4 history rows in order, and an ApprovalTask row.
6. Cross-module: `broadcast leave_approved` received by CRM activity log with
   no module code change beyond step rows (BR-COM-01 AC5 reuse).
7. Role gating: a user without SA_LEAVE_APPROVE does not see the Approve
   transition in `allowed_transitions`.

## Non-Functional

- PHP 7.3 floor; PSR-4 `ksfraser\FrontAccounting\Common\Workflow\`;
  definitions serializable array→JSON for versioning.
- Uses FA transaction wrappers and FA security areas only — no new auth.
- Timeline: history growable; index `(record_type, record_id, seq)`.

## Related

- BR-COM-01 (step-table engine + `get_dto`/`get_dto_list`); this adds the
  human/state layer on top.
- BR-COM-03 (notification feed — consumes `broadcast leave_approved`),
  BR-COM-04 (scheduler — consumes `wait` edges).
- BR-007 (append-only evidence discipline mirrored by history).
- S2 gap; feeds H3 leave, H1 recruitment, H6 offboarding, C4 quote
  approval, C5 helpdesk triage.