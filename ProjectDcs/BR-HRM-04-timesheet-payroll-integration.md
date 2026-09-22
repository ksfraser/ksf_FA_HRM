# BR-HRM-04 — Timesheet → Payroll Integration (H5)

**Modules:** ksf_FA_HRM, ksf_Timesheets (entity/service exists today),
substrate BR-COM-02 (approval states), BR-HRM-03 (payroll run)
**Status:** PENDING (design ratified in this BR)
**Built on:** TimesheetsService existing submit/approve/reject statuses
(`STATUS_SUBMITTED/APPROVED/REJECTED`, entity has hours + entry_date),
BR-HRM-03 element engine + batch, BR-HRM-01 leave day book,
BR-COM-02 (approvals as state machine where today flat status fields).

## Business Need / Current State (verbatim from tree)

`TimesheetsService` has `validateHours` (0<h≤24, date required) and
`submitEntry/approveEntry/rejectEntry` — status transitions on an entity, but:

- statuses are **flat enum fields + service mutators**, not the BR-COM-02
  state machine (no history, no allowed-transition rules, no audit rows),
- nothing consumes approved hours into **payroll**: no OT element feed, no
  variable-pay calc, no link to `0_hrm_payroll_entries`,
- no tie to the pay period — timesheets are per-week views, payroll is per
  batch, and nothing reconciles the two,
- gaps doc H5: "Timesheets are a separate module; not wired to HRM payroll or
  approvals."

## Business Requirement

The business requires:

1. **Approvals on the substrate** — time-entry approval moves from flat
   status setters to a BR-COM-02 process `timesheet.approval`
   (submitted→approved/rejected + history + guard rails). The existing
   service methods become **thin adapters over the engine** — same entity,
   same UX, real state (keeps the current pages working, no user-facing
   change). A rejected entry can resubmit (re-entrant gate; BR-COM-02 req 8).
2. **Period-lock guarantee** — once a pay period is **Posted** (BR-HRM-03),
   its time entries are immutable (an `posted_at` marker set on approved
   entries); any later edit attempt refused. The period boundary is the
   payroll batch period, not the week view.
3. **Variable-pay feed** — approved-OT hours (or any element marked
   `from_timesheet`) flow into the BR-HRM-03 compute engine as formula feed:
   an element `OT` with amount = `rate × approved_hours(PERIOD)`. The feed is
   a **resolver input** in the element engine, not a new payroll math — one
   compute path.
4. **Reconciliation** — the batch run reports, per employee: approved hours
   vs paid hours (inclusions/exclusions), so under/over-pay is visible and
   verifiable at run time.
5. **Notifications** — approval changes notify employee + approver (BR-COM-03)
   already? The timesheet approval action emits notify rows; deep link to the
   weekly view.

## Scope

- In scope: timesheet approval on BR-COM-02 (adapter over existing service +
  entity), posted-period lock, OT/variable-pay element feed into BR-HRM-03,
  period reconciliation, notification wiring.
- Out of scope: timesheets UI rework (keep current pages), project/costing
  allocations, contractual hour accounting, BR-HRM-03 batch engine changes
  (only the element-feed input + reconciliation view are new).

## Design

### Process wiring

```php
// TimesheetsService (unchanged signature) now delegates:
//   submitEntry  -> engine->transition(entry, 'submitted', actor)
//   approveEntry -> engine->transition(entry, 'approved', approver)
//   rejectEntry  -> engine->transition(entry, 'rejected', approver, reason)
// state/history/allowed_transitions exposed on the DTO (BR-COM-02 req 2)
// registerWorkflowType('time_entry', 'timesheet') once in hooks.php
// (template traits already compose, BR-COM-01)
```

- The entity keeps `status` column (read model for the existing page); the
  state machine `state` column is the authoritative field on the same row —
  policy: **state is truth; `status` is a mirror for old-page compat** (one
  write path, BR-COM-01 ownership rule).
- `posted_at` set on approved rows when their batch posts
  (BR-HRM-03 post step); edits via `editEntry` check `posted_at IS NULL`.

### Payroll feed (BR-HRM-03 integration)

```php
// element engine input resolver (BR-HRM-03 req 6/7):
$feed = [
  'OT' => ['kind' => 'from_timesheet',
           'hours' => $approvedHoursInPeriod,   // approved + not-yet-posted
           'rate'  => $otRate],                 // from salary structure formula
];
// engine: for elements with `from_timesheet`, amount = rate × feed.hours
// populated as entry; is_taxable/affects_gross honored (BR-HRM-03 math).
```

Reconciliation view per batch: employee, approved_in_period, hours_paid,
delta, notes (unapproved/partially-posted excluded) — visible in the run's
detail page before Post.

### Notifications

Approve/reject post-actions emit `notify` rows (BR-COM-03): subject lines
`Timesheet approved/rejected`, deep link `?section=week&employee=<id>`. Done
as transition post-actions (data-defined), no module code beyond steps.

## Supporting FRs

- FR-HRM-004-001 timesheet approval as BR-COM-02 process (adapter over
  existing service; state=truth, status=mirror)
- FR-HRM-004-002 posted-period lock (`posted_at` guard on edits/approvals)
- FR-HRM-004-003 from_timesheet element feed into BR-HRM-03 engine
  (OT rate × approved hours)
- FR-HRM-004-004 per-batch reconciliation (approved vs paid, delta)
- FR-HRM-004-005 approval notifications (BR-COM-03 rows)
- FR-HRM-004-006 unit/UAT

## Acceptance Criteria (UAT)

1. Unit: submit/approve/reject through the adapter preserve old page behavior
   AND write BR-COM-02 history; resubmit allowed after rejection.
2. Unit: editing an approved entry in a **Posted** batch is refused; approved
   in a Draft-era batch (batch not posted) is editable.
3. Unit: OT feed computes `rate × approved_hours(period)` into the entry and
   gross; a rejected entry's hours are excluded from the feed.
4. Unit: reconciliation shows employee/hr delta; an unapproved entry shows in
   delta with 'unapproved' note but never in hours_paid.
5. e2e (live FA container, mirrors `e2e_hrm_event_windows.php`): create two
   entries (one approved, one rejected) → run batch → assert paid hours = 1
   entry, reconciliation lists both, posted lock freezes edits.

## Non-Functional

- PHP 7.3 floor; PSR-4 additions under `HRM\TimesheetLink\` (cross-module,
   owned by HRM per H5); FA `db_*` only; `0_` SQL literal.
- No schema change to ksf_Timesheets tables beyond the `state` + `posted_at`
   columns on its time-entry table (adapters, not forks).

## Related

- BR-HRM-03 (element feed + batch post sets the lock), BR-COM-02 (approval
  substrate), BR-COM-03 (notify), ksf_Timesheets existing entity/service
- H5 gap; feeds BR-HRM-03 payslip accuracy and H7 reporting (hours summary).