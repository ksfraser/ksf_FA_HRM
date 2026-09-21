# UC-HRM-007-001 — HRM classifies employees vs contractors at a closed training

@BABOK Related: BR-007; FR-HRM-007-001 (responder).
Status: Approved — BABOK; implementation parks next stage.
Module: ksf_FA_HRM (responder).

## Preconditions
- A training category event (linked_entities[] carries the HR track) closes;
  attendees = 2 employees + 1 contractor.

## Main flow
1. Calendar closes + broadcasts (UC-CAL-007-001).
2. Timesheets subscriber asks classification; HRM resolves via
   `0_crm_persons.email → 0_hrm_contacts_employment`: 2 employees → member,
   1 contractor → external.
3. Bulk timesheet form shows the 2 employees; the contractor is not auto-timed.
4. HRM appends the read-only worked-window view for the 2 employees.

## Alternate flows
- **2a. No HR linkage:** HRM stays silent; other responders/fallback decide.

## Postconditions
- Employees auto-timed on the training track; contractor excluded from time;
  worked-window evidence appended; no cross-table writes.

## Acceptance
- ARI: exactly 2 members classified; contractor external; evidence rows for 2.