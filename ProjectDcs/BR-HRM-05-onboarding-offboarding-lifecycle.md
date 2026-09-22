# BR-HRM-05 — Onboarding / Offboarding Lifecycle (H6)

**Modules:** ksf_FA_HRM, substrate BR-COM-02 (lifecycle processes), CRM
persons (0_crm_persons as the employer record), ksf_FA_CRM/Common notify
**Status:** PENDING (design ratified in this BR)
**Built on:** `fa_contacts_employment` (hire_date, probation_end_date,
separation_reason_id exist today), `fa_separation_reasons` (defaults seeded),
BR-COM-02 state machine, BR-COM-03 notices, BR-HRM-02 (hired → this flow).

## Business Need / Current State (verbatim from tree)

`fa_contacts_employment` already has `hire_date`, `probation_end_date`,
`separation_reason_id`; `fa_separation_reasons` seeds default reason rows.
But there is:

- **no checklist** (tasks to complete when someone joins: accounts, kit,
  training, badge),
- **no confirmation event** at probation end (probation flag is data only),
- **no offboarding process** (exit interview, handover, kit return, final
  leave/payroll cut — flows that Odoo/Dolibarr automate),
- gaps doc H6: "Nothing beyond probation flags in emp page."

## Business Requirement

The business requires:

1. **Employee lifecycle = BR-COM-02 process** — `employee.lifecycle` over
   the employment row: `onboarding→active→(probation)→confirmed→separating→
   separated`; `hired` from BR-HRM-02 transitions to `onboarding`; `active`
   on hire_date; `confirmed` at probation_end (auto, via BR-COM-04 job) or
   manual.
2. **Checklists as data** — per-stage task lists (`0_hrm_checklist_tasks`
   seeded per stage: onboarding tools/accounts/training; offboarding exit/
   handover/kit/leave-cut). Task instances created when the stage is entered;
   complete/uncomplete live on the instance (audit of who-when).
3. **Stage entry = actions** — transition post-actions run the real work as
   resolvers: onboarding → create FA user (via RBAC hook), assign default
   team/role, notify HR+IT; separation → cut leave balance view, compute
   final payroll proration (BR-HRM-03), notify supervisors.
4. **Archival on separate** — separation writes `separation_reason`, exit
   date, final-day; the record locks routine workflows (no new leave requests
   after last day; leave balance freezes) via the process guard.
5. **Notifications** — stage changes notify HR; checklist item completions
   notify the owner (BR-COM-03 rows).

## Scope

- In scope: lifecycle ProcessDefinition, checklist tables + instance engine,
  stage-entry resolvers (RBAC user creation, role/team defaults, final leave
  cut, payroll proration call), separation archival + workflow locks,
  notifications, unit/UAT.
- Out of scope: document artifacts (S6), offboarding tasks taxonomy edits in
  UX (seeded data editable as data), BR-HRM-08 portal-facing checklist
  (portal renders the same instances read-only).

## Design

### Tables

```sql
CREATE TABLE IF NOT EXISTS `0_hrm_checklist_tasks` (    -- catalog (data)
  `task_id`    INT(11) NOT NULL AUTO_INCREMENT,
  `stage`      VARCHAR(24) NOT NULL,          -- 'onboarding'|'offboarding'
  `title`      VARCHAR(160) NOT NULL,
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `0_hrm_checklist_instances` (  -- per-employee rows
  `instance_id` INT(11) NOT NULL AUTO_INCREMENT,
  `person_id`  INT(11) NOT NULL,
  `process`    VARCHAR(24) NOT NULL,           -- 'onboarding'|'offboarding'
  `item_key`   VARCHAR(120) NOT NULL,          -- task catalog key
  `done`       TINYINT(1) NOT NULL DEFAULT 0,
  `done_by`    INT(11) NULL,
  `done_at`    DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`instance_id`),
  KEY `idx_person_process` (`person_id`, `process`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Process wiring

```php
// employee.lifecycle ProcessDefinition (BR-COM-02 registry)
states: onboarding -> active -> confirmed -> separating -> separated
start:  'onboarding'  when BR-HRM-02 'hired' broadcast arrives for person
auto:   onboarding->active on hire_date (BR-COM-04 clock job)
auto:   active->confirmed at probation_end (BR-COM-04) or manual confirm
user:   confirmed->separating (Initiate separation, reason + exit date)
auto:   separating->separated (final pay + leave cut done; guard:
        payroll final runs exist AND kit/exit instances all done)
post-actions:
  [onboarding entered] -> [call 'rbac.create_user']
                          [call 'notify' HR+IT]
  [separating entered] -> [call 'hrm.leave.freeze_balance']
                          [call 'hrm.payroll.final_prorate']
  [separated]          -> [call 'notify' supervisors]
```

- Checklist instances are materialized in the same transition post-action
  (catalog scan → rows per person/stage), so the engine stays data-driven.

### Locks (guards)

- Separated person: `guard` on leave-request/batch processes rejects
  `person_id` in separated/after-exit-date (BR-COM-02 guard on shared
  substrate; the leave process consult state via `get_dto`).

## Supporting FRs

- FR-HRM-005-001 employee.lifecycle ProcessDefinition + hired entry
- FR-HRM-005-002 checklist catalog + instance engine (stage-entry
  materialization)
- FR-HRM-005-003 stage-entry resolvers (RBAC user, role/team defaults,
  final leave cut, payroll proration)
- FR-HRM-005-004 separation archival + post-separation workflow locks
- FR-HRM-005-005 notifications (BR-COM-03 rows)
- FR-HRM-005-006 unit/UAT

## Acceptance Criteria (UAT)

1. Unit: hire broadcast → employee in `onboarding` with checklist instances
   materialized; RBAC user-create resolver called once.
2. Unit: hire_date passes → `active`; probation_end passes → `confirmed`.
3. Unit: separating requires reason + exit date; separated blocked until
   checklist all-done + final payroll run exists.
4. Unit: a separated person's leave request is refused (workflow guard).
5. e2e (live FA, `e2e_hrm_event_windows.php` mirror): hire → onboarding →
   active → confirmed → separating → separated; assert state rows, checklist
   instances, freeze + final-prorate resolver invocations logged, notice rows.

## Non-Functional

- PHP 7.3 floor; PSR-4 `HRM\Lifecycle\`; FA `db_*` only; `0_` SQL literal.
- RBAC/IT actions via `hook_invoke` (`rbac.create_user`) — never hard-coded FA
  internals; missing responder = no-op (BR-007 silence discipline).

## Related

- BR-COM-02 (the lifecycle substrate), BR-HRM-02 (hired entry feed),
  BR-HRM-01 (leave freeze), BR-HRM-03 (final payroll proration),
  BR-COM-04 (hire-date/probation clock jobs)
- H6 gap; feeds H8 portal (employee sees own checklist), H9 (exit artifacts).