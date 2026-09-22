# BR-HRM-01 — Leave Approval Workflow (H3)

**Modules:** ksf_FA_HRM (pages, service, repository, migration), substrate
from ksf_FA_Common (BR-COM-01/02/03/04)
**Status:** PENDING (design ratified in this BR)
**Built on:** BR-COM-02 approval state machine (transitions, guards, history),
BR-COM-01 (DTO factory / `get_dto`, engine steps), BR-COM-03 (notifications),
BR-COM-04 (accrual job); follows the leave step-table in BR-COM-01 §worked
example and BR-COM-02 §leave-process definition.

## Business Need / Current State (verbatim from tree)

`pages/leave.php` is **52 lines, read-only**: it lists `leave_balances`
(entitlement / used / balance) joined to persons + leave types. It has:

- no leave **request** — no request table exists (only `ksf_hrm_leave_balances`
  in `sql/ksf_hrm_leave_balances.sql`; the page reads `TB_PREF.leave_balances`),
- no approver chain, no submit/approve/reject, no days-with-balance check, no
  absence calendar, no notification when an approval happens.

OrangeHRM/Dolibarr/Odoo all ship full leave workflows; the gaps doc flags H3
High. The substrate BRs (S1–S4) are the mechanism, so this BR is the first
**vertical** realization: real leave records + the state machine + inbox.

## Business Requirement

The business requires:

1. **Leave requests** — an employee submits a request `(person_id,
   leave_type_id, from, to, days, reason)`; the request rows live in a real
   table (`0_hrm_leave_requests` + `0_hrm_leave_request_days` for
   date-level bookkeeping). This is the missing root: balances alone cannot
   be approved.
2. **Entitlement guard at submit** — `days_requested <= balance_remaining`
   for that type × year at submit time; the *approve* guard re-checks the
   still-current balance at the moment of approval (a parallel request may
   have consumed it) using BR-COM-02's `guard` + a CalcRegistry resolver
   (`hrm.leave.check_balance`).
3. **Approval workflow = BR-COM-02 process** — the leave module registers the
   `leave_request.approval` ProcessDefinition (states
   draft→submitted→pending→approved/rejected/rejected_exhausted→scheduled,
   transitions exactly as the BR-COM-02 worked example). Each
   `leave_request` DTO in-flight exposes `state`, `allowed_transitions`,
   `history` (BR-COM-02 req 2).
4. **Approver chain** — `pending` goes through the employee's `reports_to`
   (OrgHierarchyService) chain: each level approves (user trigger via page
   buttons), the whole chain completes → `approved`. Override/alternate
   approvers via an optional `approver_id` like BR-COM-02's step-table example
   (auto step resolves the next person). The chain is a transition *pre-
   action* (resolver `hrm.approval_chain.nextStep`), not new process logic.
5. **Notifications (BR-COM-03)** — `submitted` → next approver in chain;
   `approved`/`rejected` → requester + delegating admin. Published as
   `notify` rows (notify verb in transition post-actions) — deep link opens
   the leave request page.
6. **Absence calendar** — after `scheduled`, BR-COM-04's accrual/consumption
   job (or a transition `post` action) writes each day of the request as a
   consumed day in `0_hrm_leave_request_days` and posts to the HRM
   `ksf_hrm_leave_balances.used_days`; a calendar view renders approved
   requests per team (BR-COM-02 leave step-table row 6: approved →
   scheduled → `hrm.timesheet.create_work_window` analog becomes
   `hrm.leave.consume_days`).
7. **Balance integrity** — a `rejected_exhausted` auto-transition fires when a
   parallel request already consumed the balance (BR-COM-02 guard); the
   message names the blocker request.
8. **Access** — submit/approve pages behind FA security areas
   (`SA_LEAVE_VIEW/SA_LEAVE_APPROVE`), and a `get_dto` schema for
   `leave_request` so the designer/inbox can enumerate fields (BR-COM-01).

## Scope

- In scope: request tables + migration, submit page, my-requests list,
  approval page (allowed-transition buttons), notification wiring, balance
  guard resolvers, date-day consumption + minimal calendar view, Process-
  Definition registration, UAT.
- Out of scope: accrual policy engine (BR-COM-04 seeds balances; allocation
  math is a follow-on), absence-calendar UI polish, email (BR-COM-03 v1 is
  in-app only), payslip integration.

## Design

### Tables (migration, `0_` literal for FA install)

```sql
CREATE TABLE IF NOT EXISTS `0_hrm_leave_requests` (
  `request_id`   INT(11) NOT NULL AUTO_INCREMENT,
  `person_id`    INT(11) NOT NULL,
  `leave_type_id`INT(11) NOT NULL,
  `from_date`    DATE NOT NULL,
  `to_date`      DATE NOT NULL,
  `days`         DECIMAL(5,2) NOT NULL,
  `reason`       VARCHAR(255) NULL,
  `state`        VARCHAR(24) NOT NULL DEFAULT 'draft',   -- BR-COM-02 facing
  `current_approver_id` INT(11) NULL,
  `created_at`   DATETIME NOT NULL,
  `updated_at`   DATETIME NOT NULL,
  PRIMARY KEY (`request_id`),
  KEY `idx_person_state` (`person_id`, `state`),
  KEY `idx_from_to` (`from_date`, `to_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `0_hrm_leave_request_days` (  -- append-only day book
  `day_id`    INT(11) NOT NULL AUTO_INCREMENT,
  `request_id`INT(11) NOT NULL,
  `date`      DATE NOT NULL,
  `consumed`  DECIMAL(5,2) DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`day_id`),
  UNIQUE KEY `idx_req_date` (`request_id`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

(The FK/historical integrity mirrors `0_hrm_event_windows` append-only
discipline in BR-007.)

### Workflow wiring (fold every BR-COM-02 worked example into one page section)

- Module `hooks.php` composes the template traits once:
  `WorkflowHooksTrait` + `ProvidesDtosTrait` + `ProvidesNotifierTrait`
  (BR-COM-01 `Template wiring`), registers `leave_request` DTO builders
  (repository-backed, schema from `TableDefinition` for the new tables).
- `hooks.php` registers the `leave_request.approval` ProcessDefinition
  (BR-COM-02 registry shape): states/transitions from the BR-COM-02 worked
  example, with post-actions = `notify` recipients (roles/current_approver/
  requester) and `hrm.leave.consume_days` on approved→scheduled.
- Approval page buttons = `allowed_transitions` from the DTO (BR-COM-02
  req 3): Approve / Reject / Cancel render only when legal + user has the
  security area; each submits to
  `Scheduler/StateMachine->transition($dto, 'pending')->to, actor, reason`.
- Submit page: build DTO (fields from schema), set `state=submitted`,
  fire `after_save` → start condition runs the process (BR-COM-02 §start).

### Sequence (the vertical slice)

1. Employee opens Submit, picks type/dates; client + service compute `days`.
2. `hrm.leave.validate_fields` (auto start guard) rejects
   `days > balance_remaining` → stays draft with error.
3. Process starts (submitted) → auto `nextStep` promotes to `pending` for the
   first chain approver → `notify` row lands in that user's inbox (badge +1,
   BR-COM-03 UI).
4. Approver opens inbox → deep link → approval page shows state + history +
   Approve/Reject buttons (guard re-checks balance; parallel consumption →
   `rejected_exhausted` auto instead).
5. Approve for approving: transition moves `pending→approved` when it was the
   last chain level (resolver decides), else next level, `notify` each hop.
   Reject: `pending→rejected` with reason, `notify` requester.
6. On `approved` the `consume_days` post-action materializes
   `0_hrm_leave_request_days` + decrements `used_days` balance. A calendar
   view lists per-team approved date ranges.

## Supporting FRs

- FR-HRM-001-001 request tables + migration (`0_` literal, append-only day
  book)
- FR-HRM-001-002 submit page (DTO via `get_dto`, validate_fields guard,
  submit → after_save start)
- FR-HRM-001-003 approval page (allowed-transitions buttons, security-area
  gating, reason-on-reject)
- FR-HRM-001-004 approver-chain pre-action
  (`hrm.approval_chain.nextStep` over OrgHierarchyService reports_to)
- FR-HRM-001-005 balance guard resolvers (`check_balance`, exhaustion
  auto-reject naming the blocker request)
- FR-HRM-001-006 notification wiring (BR-COM-03 rows; deep link; each hop)
- FR-HRM-001-007 consume_days post-action + calendar view
- FR-HRM-001-008 ProcessDefinition + `get_dto` registration in hooks.php
- FR-HRM-001-009 unit + UAT (per AC)

## Acceptance Criteria (UAT)

1. Unit: Submit with `days > balance_remaining` stays draft (guard); with
   available balance it enters `submitted` → auto `pending` for chain head.
2. Unit (approve-path): 2-level chain — first approve moves to second level,
   second approve → `approved`; `0_hrm_leave_request_days` populated on the
   approved→scheduled common path (consume_days), `used_days` corrected.
3. Unit (reject-path): Reject (with reason) → `rejected`; parallel request
   that consumed the balance → auto `rejected_exhausted` with blocker name.
4. Unit: a user without `SA_LEAVE_APPROVE` sees no Approve/Reject buttons;
   direct `transition` call without the area is refused (BR-COM-02 AC7).
5. e2e (live FA container, mirrors `e2e_hrm_event_windows.php`): seed person
   + balance, submit → assert state rows + history; approve chain to
   `approved`; assert notifications row for final approver + requester;
   assert consumed days rows + reduced balance.

## Non-Functional

- PHP 7.3 floor; PSR-4 `HRM\Leave\`; runtime uses FA `db_*` only; tables use
  literal `0_` (FA install mechanics — `db_import()` rewrites `0_` only).
- Developer-preview calendar = simple HTML table (no new asset pipeline).
- In-app notifications only in v1 (mail via BR-COM-03 hooks later).

## Related

- BR-COM-02 (the state machine it brings to life), BR-COM-01 (`get_dto`
  schema + designer), BR-COM-03 (inbox), BR-COM-04 (accrual seed + timesheet
  edge), BR-007 (append-only discipline echo), BR-006 (DDL caching for new
  tables)
- H3 gap; feeds H5 timesheet integration (uses the same day book) and H9
  (contract artifacts for approved/rejected audit trail).