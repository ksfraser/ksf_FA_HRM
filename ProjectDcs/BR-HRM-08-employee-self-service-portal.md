# BR-HRM-08 — Employee Self-Service Portal (S5 + H9)

**Modules:** ksf_FA_HRM (portal pages), ksf_FA_RBAC/FA session (login/auth),
FA security areas; substrate BR-COM-01 (get_dto), BR-COM-03 (inbox)
**Status:** PENDING (design ratified in this BR)
**Built on:** FA's existing user→person association (`wa_user.employee_id` in
the timesheets page reads the employee id), BR-HRM-01 (leave requests),
BR-HRM-05 (my onboarding/offboarding), BR-HRM-07 (my certifications),
BR-COM-03 (my inbox). Same session, no new auth.

## Business Need / Current State

OrangeHRM's ESS (leave/payslip/self-data), Odoo portal, Dolibarr external
area all let the employee act on their own records. Our FA sessions already
carry an `employee_id` (timesheets page uses it), but there is **no portal
page set**: everything is administered by HR on the admin pages. S5+H9 is
the employee-facing skin over the substrate work: self leave requests
(BR-HRM-01 already end-to-end), view payslips (BR-HRM-03), my inbox
(BR-COM-03), my checklist state (BR-HRM-05), my certificates/expiry
(BR-HRM-07), and contact-data review (own `0_crm_persons`).

The portal is **not a new module**: it's a set of employee-scoped pages in
ksf_FA_HRM that read the same resolvers/repos but filter by
`current-user employee_id` (defense-in-depth via FA page security + a `owner
check` in every resolver contract — no trust in the URL id).

## Business Requirement

The business requires:

1. **Portal shell** — a dedicated practical section: `ess/` pages under FA
   with a `MY_<module>` set of security areas (view-only + self-action
   granularity). Every query is scoped `person_id = current employee`,
   **server-side enforced**.
2. **My leave** — submit request (BR-HRM-01 same flow), my list with state +
   history + remaining (buttons only when `allowed_transitions`); the portal
   reuses the exact submit/approval code path — no second implementation.
3. **My payslips** — view/print past payslips for the employee (BR-HRM-03
   payslip view, scoped); pay-day availability visible, no re-creation.
4. **My inbox** — BR-COM-03 rows filtered to me (badge + list + dismiss) —
   the notification subsystem's user-facing half, mounted in the portal.
5. **My checklist & lifecycle** — current stage (BR-HRM-05 state) + my
   onboarding/offboarding checklist instances (mark-mine-done where the task
   is self-doable), upcoming probation/confirmation date.
6. **My training/certs** — my enrollments + certificates with expiry (BR-HRM-07),
   expiry-soon highlighted; portal link to catalog for re-enroll (create
   enrollment).
7. **My contact data** — read-only (v1) view of own `0_crm_persons` details;
   a change request (notify HR, not direct edit) for corrections — direct
   self-edit is out of scope (data integrity).

## Scope

- In scope: ESS page shell + `MY_*` areas, my-leave (submit/list),
  my-payslips, my-inbox, my-checklist/lifecycle, my-certs, read-only contact
  strip + change-request notice, owner-scoping resolver contract,
  unit/UAT.
- Out of scope: external internet login (still FA internal sessions; a
  standalone portal endpoint is a separate future BR), self-edit of contact
  PII, admin workflows (they stay the admin pages).

## Design

### Page shell (FA-native)

- `modules/ksf_FA_HRM/ess/index.php` = portal landing (my cards: leave
  balance, next approval due, cert expiring, unread)
- `ess/leave.php`, `ess/payslips.php`, `ess/inbox.php`,
  `ess/checklist.php`, `ess/certs.php` — each `MY_*` guarded,
  scoped resolver, FA table/card renderer.
- Security areas: `SA_MY_LEAVE_SUBMIT`, `SA_MY_INBOX`,
  `SA_MY_PAYSLIPS`, `SA_MY_CHECKLIST`, `SA_MY_CERTS` — assigned to employee
  roles (data, default via hook; admins get all via existing admin areas).

### Owner scoping (the security contract)

```php
// every ESS resolver signature (CalcRegistry/BR-COM-01):
function ess_my_leave_get(int $sessionEmployeeId, array $criteria): array {
    // criteria['person_id'] is force-set to $sessionEmployeeId server-side;
    // any caller-supplied person_id is ignored (never trusted).
    // repo filters add `AND person_id = {forced}`; page passes explicit id.
}
```

Unit-proof: a test supplies `criteria['person_id'] = 999` and asserts the
forced value wins. Same contract across leave/payslip/inbox/checklist/certs
read paths (single helper `ess_scope(int $uid)`).

### Portal vs admin reuse

No duplication: portal pages call the same services/resolvers the admin pages
use (submit leave, list payrolls, inbox read, checklist engine, cert rows);
only the **scoping + button set** differs (`allowed_transitions` already gives
that — the portal simply renders only the employee-self transitions). This is
the DRY payoff of putting workflow on the substrate first.

## Supporting FRs

- FR-HRM-008-001 ESS shell + MY_* security areas + portal landing cards
- FR-HRM-008-002 my-leave (submit/list/state/buttons = allowed_transitions)
- FR-HRM-008-003 my-payslips (scoped payslip view)
- FR-HRM-008-004 my-inbox (BR-COM-03 read/dismiss)
- FR-HRM-008-005 my-checklist + lifecycle stage (BR-HRM-05 state)
- FR-HRM-008-006 my-certs + expiry reminders (BR-HRM-07) + re-enroll link
- FR-HRM-008-007 read-only contact strip + change-request notify
- FR-HRM-008-008 owner-scoping resolver contract (server-enforced, unit-proof)
- FR-HRM-008-009 unit/UAT

## Acceptance Criteria (UAT)

1. Unit (scoping): every ESS resolver ignores caller-supplied `person_id`
   and force-scopes to session employee; a cross-user id returns nothing
   (not a leak).
2. Unit: my-leave submit uses the same BR-HRM-01 path; portal shows only
   self-allowed transitions; admin page unaffected.
3. Unit: my-inbox lists only my rows; my-certs expiry-soon flagged;
   checklist shows only my instances; lifecycle stage current.
4. e2e (live FA container, mirror `e2e_hrm_event_windows.php`): as
   employee user, submit leave → approval notifies approver; load payslip
   (seeded batch) → renders; inbox new row appears; cert expiry flag shows.

## Non-Functional

- PHP 7.3; PSR-4 `HRM\SelfService\`; FA session/pages renderer only; `db_*`
  only; no second auth (uses FA session + areas).
- Every read path is scoped-server-side (unit-tested contract, not a UI
  convention). No direct PII self-edit v1.

## Related

- BR-HRM-01 (leave flow = the portal's core transaction), BR-COM-03 (inbox),
  BR-HRM-05 (checklist/lifecycle), BR-HRM-07 (certs), BR-HRM-06 (portal KPI
  cards reuse turnover resolver), BR-HRM-03 (payslips)
- S5+H9 gaps; the portal consumes the whole HRM substrate as its read/write
  surface. With this BR, every H* gap is closed.