# BR-CRM-08 — Activity Automation & Chaining (C8)

**Modules:** ksf_FA_CRM, substrate BR-COM-01 (step table engine) /
BR-COM-02 (state machines) / BR-COM-03 (notify)
**Status:** PENDING (design ratified in this BR)
**Built on:** `add_communication()` (0_fa_crm_communications) is the
commercial-actions log; `0_fa_crm_activity_log` exists; `crm_dispatch_event()`
is a thin wrapper at the top of crm_db.inc (an early hook stub). gaps doc C8:
"`communications.php`(201) exists; no suiteCRM-style activity chaining."

## Business Need / Current State

`crm_dispatch_event` (line 15 of crm_db.inc) is exactly the seam SuiteCRM's
LogicHooks sit on, but it's a **no-op dispatcher today** (no consumers). The
step-table engine from BR-COM-01 is the generalization: commercial actions
(meeting, call, lead→opp, quote→order, ticket reply, contract renew) are
**events**; C8 wires them into authorable step rows (criteria → then → do →
chain) so cross-object automation lives in data, not per-page code. This closes
the final CRM gap AND is the natural CRM-side adoption of S1.

## Business Requirement

The business requires:

1. **Event surface normalized** — every CRM action emits its event on the
   substrate (BR-COM-01 step engine): `communication.added`,
   `lead.converted`, `opportunity.won`, `quote.accepted`, `quote.converted`,
   `ticket.created`, `ticket.resolved`, `contract.renewed`. Existing
   `crm_dispatch_event()` sites & add_communication() become emitters into
   BR-COM-01 (the step engine replaces the 15-line no-op stub).
2. **Authorable step rows (data, not code)** — CRM admins author rows:
   criteria (on the DTO), then a CalcRegistry resolver, do (set/create/call/
   broadcast). Examples ship as seed steps:
   - `opportunity.won` → set `opportunity.next_action = 'send contract'`,
     create `FollowUp` DTO → chain,
   - `quote.accepted` → broadcast `contract_draft` to owner,
   - `lead.converted` → notify owner + create intro communication.
3. **Follow-up automation** — from criteria `next_followup_date <= today` →
   run a step that creates a communication-action DTO (the existing
   follow-up list `get_pending_followups` becomes a query over the same
   event/rows, not a parallel mechanism).
4. **Guard rails** — cap depth + visited-set + fault tolerance reuse
   BR-COM-01 req 5 verbatim; loop safety proven on the seed steps.
5. **Audit** — every step fired logs into `0_fa_crm_activity_log`
   (append-only) with `(event, dto_type, dto_id, step, at)` — the activity
   chain is both the automation ledger and the "commercial-actions history"
   the gap doc asks for.

## Scope

- In scope: event normalization onto BR-COM-01, seed step rows (the 4
  examples), follow-up automation view, activity-log audit rows, designer
  min-surface (JSON authoring of step rows — BR-COM-01 FR-COM-01-007),
  unit/UAT.
- Out of scope: full drag-drop designer (BR-COM-01 designer v1 is JSON),
  email automation sequences (BR-CRM-04 drip is a future C2.1), escalation
  rules beyond C5's own.

## Design

### Event → step wiring

```php
// crm_db.inc emitters now call:
// ksf_fa_common step engine: engine->runSteps('crm.communication.added', $dto)
// (rename of the 15-line crm_dispatch_event stub; same seam, real engine)
// dto types: CommunicationDto, LeadDto, OpportunityDto, QuoteDto,
//            TicketDto, ContractDto  (via BR-COM-01 get_dto)
```

Seed step rows (registry data):

| # | Event | Criteria (IF) | Then (resolver) | Do | Else |
|---|-------|---------------|-----------------|----|------|
| 1 | opportunity.won | `winner == owner` | `crm.nextaction.contract` | set `next_action='send contract'`; create `FollowUp` | no-op |
| 2 | quote.accepted | — | — | broadcast `contract_draft` (owner+C6) | no-op |
| 3 | lead.converted | — | — | notify owner (BR-COM-03); create `Welcome` comm | no-op |
| 4 | followup due | `next_followup_date <= today` | — | create `CommunicationAction` DTO (call owner) | skip |

### Follow-up query reuse

`get_pending_followups()` is re-implemented as a `get_dto_list` criteria over
the DTOs (owner, due) — one query path; the old direct SQL stub is removed
(deduped with C7 email/list).

### Activity chain ledger

Every step run appends `0_fa_crm_activity_log`:
`(event, dto_type, dto_id, step_name, resolver, at, by_uid)`. The commercial-
actions history page (`communications.php` + a new "Automation" section)
renders this — one append-only chain the whole module shares.

## Supporting FRs

- FR-CRM-008-001 event surface normalization onto BR-COM-01 (emitters from
  the 7 actions incl. replacing crm_dispatch_event no-op)
- FR-CRM-008-002 seed step rows (won/accept/convert/followup examples) authorable
- FR-CRM-008-003 follow-up automation query (get_dto_list rework of
  get_pending_followups)
- FR-CRM-008-004 activity-chain audit (0_fa_crm_activity_log append-only)
- FR-CRM-008-005 designer JSON min-surface + validated seed steps
- FR-CRM-008-006 unit/UAT incl. loop/depth guard proof on seeds

## Acceptance Criteria (UAT)

1. Unit: opportunity.won fires step 1 (sets next_action, creates FollowUp);
   quote.accepted fires step 2 broadcast; lead.converted fires step 3 notify.
2. Unit: follow-up due step creates the CommunicationAction row only when due;
   get_pending_followups returns the same rows as the old stub on seeded data.
3. Unit: loop guard — a seed step that would re-enter same event hits
   visited/depth cap (BR-COM-01) and logs the guard hit, no infinite loop.
4. Unit: every fired step appends an activity_log row (event/dto/step/at).
5. e2e (live FA container, mirror `e2e_hrm_event_windows.php`): drive
   opportunity→won + quote accepted on seeded rows → assert next_action set,
   FollowUp created, contract_draft broadcast received, activity_log rows in
   order; follow-up DTO appears in pending list.

## Non-Functional

- PHP 7.3; PSR-4 `CRM\Automation\`; FA `db_*` only; `0_` SQL literal;
  step rows are registry data (authorable-as-data, BR-COM-01 req 8); no code
  moves for new automations.
- Activity chain append-only; single ledger for automation + history.

## Related

- BR-COM-01 (the engine all of C8's steps live on), BR-COM-03 (notify in
  steps), BR-CRM-01/03/05/06 (the events), BR-CRM-07 (dashboard consumes the
  activity ledger)
- C8 gap; final CRM BR — the commercial-actions history + automation parity
  with SuiteCRM LogicHooks/WorkFlows (the "activity stream" the gap doc
  names), closing all 20 roadmap items.