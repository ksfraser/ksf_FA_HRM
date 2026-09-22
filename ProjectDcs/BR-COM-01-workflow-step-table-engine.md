# BR-COM-01 — Workflow Step-Table Engine (SuiteCRM-Inspired Lifecycle Hooks)

**Modules:** ksf_FA_Common (host), ksf_FA_HRM, ksf_FA_CRM, and any consumer
module switching on record lifecycle events
**Status:** PENDING (design ratified in this BR)
**Prerequisite chain:** BR-007 (event-close hook protocol) — this generalizes
BR-007's responder pattern into an authorable step table.

## Business Need

Since BR-007, modules handle record lifecycles by hand-writing responders
into `hooks.php` (`ksf_event_closed`, `ksf_event_classify_attendees`,
`order_imported`, …). Every such responder is bespoke: the hook fires, a
service decides whether it applies, computes, and mutates/creates. There is
no shared, authorable convention describing **when** (which event), **under
which criteria** (fields on the DTO), **what to compute** (calls/calculations),
and **what to do with the result** (modify the DTO, create another DTO, fire
the next hook). Each module re-invents orchestration, so cross-module chains
(commission → payroll, leave → approval → notification) cannot be composed or
audited uniformly.

SuiteCRM solves exactly this with **LogicHooks**: when a bean is loaded or
saved, an ordered list of hooks fires; each hook tests criteria on the bean,
runs logic, and may mutate the bean or spawn records that fire their own hooks.
We adopt that model.

## Business Requirement

The business requires a **generic, authorable workflow step-table engine**:

1. **Lifecycle event points** per record type: on-load (`after_retrieve`),
   pre-save (`before_save`), post-save (`after_save`), pre-delete
   (`before_delete`), post-delete (`after_delete`), plus relationship/link
   events (`linked`, `unlinked`) — matching SuiteCRM's hook surface.
2. **Ordered, named steps** per (record type, event): each step is a row with
   (a) criteria (IF — conditions over DTO fields), (b) an action (THEN —
   calculation or service/function call), and (c) result handling (DO — set
   DTO field, create another DTO, fire the next hook, or broadcast).
3. **Chaining**: an action may create a new DTO (or modify the current one,
   or set a field that later criteria depend on); created DTOs fire their own
   `after_save`/`after_retrieve` steps — hooks fire again, exactly as the user
   specified.
4. **Determinism + audit**: steps run in declared order per event; every step
   execution (criteria matched/not, action result) is loggable for replay.
5. **Fault tolerance**: a failing step never aborts the caller's hook loop
   (BR-007 REQ-5 discipline); failures are logged and continue.
6. **No loop-escape hazard**: chaining is bounded (max depth + visited-DTO
   guard) so a cycle (A creates B creates A) cannot spin.
7. **Cross-module without new infra**: the registry lives on the existing
   `hook_invoke_all` / `{prefix}_{hook}` substrate in
   `ksf_fa_common/src/Traits/WorkflowHooksTrait.php`; no new dispatcher.
8. **Authorable as data**: a workflow is a PHP array (or JSON, serializable)
   of steps, registered per record type — services/calls referenced by name,
   injectable via DI, so unit tests can stub each action.

## Scope

- In scope: the step-table schema, registry, lifecycle event points,
  chaining, guard rails, and the worked examples below (leave submit →
  approve, sale order → commission, event closed → worked-window evidence).
- Out of scope (follow-on BRs): human multi-approver **state machines with
  persistence** (BR-COM-02), **notifications/activity feed** (BR-COM-03),
  **scheduled/cron automation** (BR-COM-04). This BR provides their
  trigger-and-chain substrate.

## Design — The Step Table

The authoring format (canonical; used verbatim in workflow-capable BRs and
FRs). A workflow is: **record type → event → ordered steps**. Each step is one
row of this table:

| # | Event/Hook | Criteria (IF — conditions over DTO fields) | THEN: calculation / service call | DO with result (IF/ELSE on result or side channels) | Notes |
|---|-----------|--------------------------------------------|-----------------------------------|-----------------------------------------------------|-------|
| 1 | `after_save` `order` | `status == 'shipped'` AND `commission_status` empty | `bill = resolveBill(order)`; `rate = bill->rate`; `amount = percent(order.total, rate)` | set `order.commission_amount = amount`; set `order.commission_status = 'computed'`; create `CommissionEntry` DTO (person, order, amount) → fires `after_save` (chain) | matches BR-007/order_imported behaviour, now declarative |
| 2 | `after_save` `leave_request` | `status == 'submitted'` AND `approver_id` set | `wf = queryActiveApproverChain(department)`; `next = wf->step(1)` | set `leave_request.current_approver = next.person_id`; set `leave_request.status = 'pending'`; create `ApprovalTask` DTO → fire `after_save` | S2 state machine bootstrap |
| 3 | `after_save` `leave_request` | `status == 'approved'` AND `hours_requested > 0` | `bal = calcBalance(person, type)`; `check = bal.remaining >= hours_requested` | IF `check`: set `status = 'scheduled'`; create worked-window evidence per BR-007. ELSE: set `status = 'rejected_exhausted'`; set `reason = 'no balance'` | prevents overdraw on approve |
| 4 | `after_retrieve` `opportunity` | stage probability read | `expected = weightedForecast(amount, probability)` | set `opp.expected_revenue = expected` (derived, cached, never persisted) | KPI/forecast |
| 5 | `after_save` `employee` | `termination_date` set AND previously null | `aud = notify(hr, employee)`; `story = archive(employee)` | create `TerminationRecord` DTO → fire `after_save`; log activity | offboarding chain seed (H6) |

### Step-row semantics

- **Criteria (IF)** — zero or more predicate triples `(field, op, value)` over
  the DTO (also `isset`/`empty`), AND-combined per row; `ELSE` clause may
  point to an alternative next step.
- **THEN** — a named calculation or service call, resolved by a small
  resolver (`CalcRegistry`) accepting a `Callable` or `[service, method]`;
  receives the DTO and returns a result (value, void for side effects, or
  throwable → logged per requirement 5).
- **DO** — the post-action dispatcher. Default = assignment of a field on the
  (object|array) DTO via a DTO-accessor (same shape rule as
  `EventEmployeeMembershipService::dtoValue`: array | `toArray()` | `getX()`
  | `get(key)`). Special DO verbs:
  - `set` — assign `field = value` on the DTO (mutates, fires nothing).
  - `create` — build a fresh DTO from a spec + optionally copy fields; the
    create triggers `before_save`→`after_save` on the new record (chain).
  - `call` — invoke a service method with the DTO / result (side-effect only).
  - `broadcast` — `hook_invoke_all($name, $payload)` to any listening module.
- **ELSE** — optional; if present, evaluated when criteria fail; may `set`,
  `create`, `call`, `broadcast`, or `end`.

### Registry + wiring

```php
// ksf_fa_common/src/Registry/WorkflowRegistry.php (host)
$registry = WorkflowRegistry::getInstance();
$registry->register('leave_request', 'after_save', [
    ['criteria' => [['status','eq','submitted'], ['approver_id','isset']],
     'then'     => ['resolver' => 'hrm.approval_chain', 'method' => 'nextStep'],
     'do'       => [['set','current_approver','=','result.person_id'],
                    ['set','status','=',  'pending'],
                    ['create','ApprovalTask','copy'=>['request_id','current_approver']]]],
    // …more steps, in order
]);
```

The engine subscribes once: the owning module's `hooks.php` registers
`hook_invoke_all('after_save', $dto)` legs per record type through
`registerWorkflowType($recordType, $hookPrefix)` (existing
`WorkflowHooksTrait`), and the engine runs the ordered steps for
`(recordType, event)`. A step's `create`/`broadcast` re-enters the same
engine → **hooks fired again** (the user's required chaining).

### System-wide DTO access — `get_dto` / `get_dto_list` (SuiteCRM BeanFactory analog)

The `create`/`call` DO verbs and the flow designer must be able to produce and
inspect DTOs for **any activated module** without the workflow engine holding
module-specific code. SuiteCRM solves this with `BeanFactory::getBean($module,
$id)` + generic list retrieval over vardefs. We adopt the same "system-wide
front door, module-authored internals" shape:

- **`hook_invoke_all('get_dto', $payload, $opts)`** — request one DTO.
  `$payload = ['module' => 'ksf_FA_HRM', 'type' => 'leave_request',
  'id' => 7]`. The owning module's responder returns
  `$payload['dto'] = LeaveRequestDto` (or null). Contract:
  `get_dto` returns the **fillable DTO** (all writable fields present,
  regardless of persistency — a `toArray()`-ready object per the dtoValue
  accessor shape), plus `$payload['schema']` = field list + labels derived
  from the data dictionary (`ksf_common_db` `TableDefinition`), so a designer
  can enumerate what is fillable.
- **`hook_invoke_all('get_dto_list', $payload, $opts)`** — request a list.
  `$payload = ['module' => ..., 'type' => ..., 'criteria' => [predicates],
  'limit' => 10]`. Responder sets `$payload['dtos'] = array<Dto>` (typically
  via the owning repository + `QueryBuilder` filters). Used by the designer's
  browse UI and by `do=call` resolvers that need evidence lists
  (e.g. "last 3 worked windows for person").
- **Silence = empty**: if no module responds, `dto` is null / `dtos` is
  `[]` — chain continues (BR-007 REQ-5; never throw).
- **Schema sourcing**: field metadata (name, type, label, required, writable)
  is NOT duplicated in the registry — `get_dto` lazily pulls it from the
  data dictionary. Modules that have not migrated their DAOs can respond with
  hard-coded schema arrays until the dictionary port lands (interim rule).

### Implementation shape (decided — contract + trait + module registry)

Three-way split, following the house `DbAdapterInterface` → `FaDbAdapter` pattern:

1. **Contract (system-wide)** — `ksf_FA_Common` (package) owns
   `Common\Contract\DtoProviderInterface`: `getDto(string $type, $id)` and
   `getDtoList(string $type, array $criteria, int $limit)` returning
   `[dto|dtos, schema]`. The engine and designer depend on this interface
   only; it is the DI seam and the unit-test seam (a mock provider drives the
   engine without any module loaded).
2. **Trait (module-local glue)** —
   `Common\Workflow\ProvidesDtosTrait`: implements the `get_dto`/
   `get_dto_list` hook responders for a module's hooks class — dispatch the
   payload, look up the module's registered types, echo `dto`/`dtos`/`schema`.
   Modules register types via `registerDtoType($type, callable $builder)`
   (+ optional list-builder). A module that needs full control implements the
   contract directly and ignores the trait.
3. **Registry entries (module-specific data, never shared)** — which types
   exist, which repository/service/schema builds each DTO: module-authored
   data only (mirrors per-module repositories). The trait can carry no module
   knowledge; it only routes to what `registerDtoType()` injected.

### Template wiring — every module can be asked (the hooks composition)

The per-module **hooks.php template** (`hooks_ksf_FA_<M>` class, per AGENTS.md
FA Module Conventions) composes the shared traits so the system-wide front
doors resolve uniformly across activated modules:

```php
class hooks_ksf_FA_HRM extends hooks
{
    use ksfraser\FrontAccounting\Common\Workflow\WorkflowHooksTrait;       // lifecycle events
    use ksfraser\FrontAccounting\Common\Workflow\ProvidesDtosTrait;        // get_dto / get_dto_list
    use ksfraser\FrontAccounting\Common\Notification\ProvidesNotifierTrait; // notify (BR-COM-03)
    // ...
}
```

Effects:
1. **Every module can be asked** — `hook_invoke_all('get_dto', ...)` against
   any activated module reaches its responder, so the engine and designer
   assume one uniform front door (no module-specific dispatch code in the
   host, and no responding module that "has to know it was asked" — the trait
   does the routing).
2. **Silence until registered** — compositing the trait alone returns
   null/`[]` for unregistered types (never throws; BR-007 discipline).
   Participation is opt-in by **data** (`registerDtoType(...)` calls), not by
   extra code — a fresh module already responds correctly the day it registers
   its first type.
3. **Ownership preserved** — the trait only dispatches payloads to the
   builders the module registered (its own repositories/services). Nothing in
   the trait can mutate a foreign module's record.

Modification semantics (what "accessed/modified" means, exactly):
- **Current flow record**: `set` mutates the in-flight DTO (fires nothing);
  `create` builds a new DTO firing `before_save`→`after_save` (BR-COM-01
  DO verbs).
- **Any other module's record**: intentionally NOT a blind DTO-save from here
  — FA has no ORM dirty-tracking, and a parallel write path would race the
  owner's own save. Cross-module changes route through the owning module's
  service via `call`/CalcRegistry resolver: module B's code performs the
  mutation, keeping exactly one write path per table (SRP + BR-007
  discipline). `get_dto` gives read/modify-in-engine; `call` gives
  persist-through-owner.

This satisfies the user's requirement: the THEN part can create a new DTO for
any activated module (fields known + fillable via the returned schema), and a
designer can browse/instantiate across modules uniformly — while the engine
stays module-agnostic and every module keeps ownership of its own DTOs.

### Guard rails (requirement 6)

- `max_depth` (default 5) decremented on each re-entrant dispatch; hit → log
  + abort chain.
- `visited` set of `(recordType, id)`; a DTO re-entered while still in-flight
  is skipped (pre-orders cycles from A→B→A).
- `log_callback` hook on every step decision for audit/replay.

### Supporting FRs (to be authored with this BR)

- FR-COM-01-001 lifecycle event surface (which events per record type)
- FR-COM-01-002 criteria DSL (field/op/value predicates + ELSE)
- FR-COM-01-003 CalcRegistry resolver (named callables/services, DI)
- FR-COM-01-004 DO verbs (set/create/call/broadcast) + DTO accessor shape
- FR-COM-01-005 chaining, depth, visited-guard, fault tolerance, audit log
- FR-COM-01-006 system-wide DTO access: `get_dto` / `get_dto_list` hook
  contract (module responders, schema-from-dictionary, silence=empty)
- FR-COM-01-007 designer min-surface: list activated modules → enumerate
  fields (from schema) → author steps (criteria/then/do) → serialize to the
  registry
- FR-COM-01-008 the worked examples above as regression fixtures (unit +
  live e2e mirroring `e2e_hrm_event_windows.php`)

## Acceptance Criteria (UAT)

1. Register the four worked examples as steps; a unit test drives the DTO
   through each lifecycle and asserts: field mutations, created DTOs, and
   re-fired hooks exactly as the DO column says.
2. A cycle (record A after_save → create B → after_save → create A) aborts at
   `max_depth` with an audit log, and does not hang.
3. A step whose service call throws logs the error, the caller's loop
   continues, and the record is left in the pre-step state (no partial DO).
4. An `e2e` in the FA php7.4 container runs the leave-submit example against
   the live DB and asserts the `ApprovalTask` row + `current_approver` —
   mirroring the existing `e2e_hrm_event_windows.php` pattern.
5. Cross-module: a `broadcast('leave_submitted', $dto)` is received by CRM's
   activity log with no code change in either module beyond the step rows.
6. `get_dto('ksf_FA_HRM','leave_request',7)` populates `$payload['dto']` with
   all fillable fields and `$payload['schema']` from the data dictionary; an
   unactivated module id resolves to null/empty with the chain continuing.
7. A designer-generated step set (JSON) round-trips: serialize → re-register →
   same behaviour in unit tests (authorable-as-data requirement 8).

## Non-Functional

- PHP 7.3 floor; PSR-4 `ksfraser\FrontAccounting\Common\Workflow\*`;
  serializable step rows (array→JSON) for audit export.
- Perf: criteria evaluation is in-memory; DB involved only in resolvers.
- Security: resolvers are registered by name in an allow-list (no arbitrary
  callables from untrusted data).

## Related

- BR-007 (cross-module event-close hook; this generalizes it)
- S1 workflow engine; S2 approval state machine (BR-COM-02 builds on the
  `create ApprovalTask` leg)