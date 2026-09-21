# FR-HRM-007-001 — Employee membership responder + worked-window evidence

@BABOK Related: BR-007; FR-CAL-007-003 (responder role); feeds FR-TIME classification
          fallbacks and EmployeePay worked-window pay evidence (FR-EP-007-001).
@UML : HRM/responder -> `ksf_event_classify_attendees` for employee windows
Status: Approved — BABOK; implementation parks next stage.
Module: ksf_FA_HRM (answers employee membership for ITS tracks; read-only).

## Need (BABOK What-not-How)
For trainings, department windows and other HR-tracked meetings, HRM is the
authority on who is an EMPLOYEE (vs contractor/consultant). That partition
decides who may be auto-timed and feeds pay-run worked-window evidence —
without Timesheets or EmployeePay reading HRM tables directly.

## Requirement
1. On `ksf_event_classify_attendees`, when the event maps to an HR track
   (training/category in `linked_entities[]`), append to
   `$opts['classification']`:
   - `member` = attendee emails resolving to ACTIVE employees
     (`0_crm_persons.email → person_id → 0_hrm_contacts_employment.is_active`),
   - `external` = emails resolving to persons WITHOUT active employment
     (contractor/consultant).
2. Unresolvable emails stay unclassified (never guessed).
3. Read-only during classification; native `db_*` via the module's own DAO layer;
   fault-tolerant (errors contained, never abort the caller).
4. Optionally subscribes to `ksf_event_closed` to record a read-only
   worked-window view (person + `started_at/closed_at`) that pay runs may
   consult; it writes ONLY its own window view, never timesheet/expense/pay
   tables.

## Acceptance
- ARI: training invites 2 employees + 1 contractor -> member=[2], external=[1].
- AZZ: email not in `0_crm_persons` -> unclassified (no false member).
- BON: responder throws -> caller continues (fault tolerance).
- CAN: worked-window view rows are append-only and never mutate EventClosedDto.