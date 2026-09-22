# HR-CRM Gap Analysis — ksf_FA_HRM / ksf_FA_CRM vs Notrinos, webERP, SuiteCRM, Odoo, Dolibarr, OrangeHRM

Status: analysis as of 2026-09-22. Grounded in the actual module inventories
(pages, src/, sql/, ProjectDcs), not a generic features-of-ERP checklist.

Companion docs: `AGENTS.md` / `AGENTS_ARCH.md` (shared module conventions),
`EVENTCLOSE_FLOW.md` (BR-007 hook protocol these gaps build on).

---

## 1. Positioning note (who we are NOT behind)

- **webERP** and **Notrinos** are operations-first. webERP ships no built-in
  CRM/HRM at all; Notrinos is an FA-descended fork and neither has either.
  On accounting/FA ancestry our modules are at parity — the meaningful
  comparison is the **feature-complete suites**: **Odoo**, **Dolibarr**,
  **SuiteCRM**, **OrangeHRM**.

- FA-sourced context: everything below must respect the cross-module floor
  (PHP 7.3), the `hook_invoke_all` extension protocol, the `0_` SQL prefix
  convention, and the ProjectDcs BR/FR/UC/UT traceability discipline. The
  PLATFORM already has one of the hardest pieces — a per-row lifecycle-hook
  substrate (`WorkflowHooksTrait`, `CrudOperationsTrait`, `hook_invoke_all`)
  — that the suites took years to build. The gap is **authoring**, not raw
  mechanic.

---

## 2. Cross-cutting substrate gaps (root causes — fix first)

These four are the systemic gaps; nearly every module-level gap below is a
symptom of one of them. They are the highest-leverage place to invest.

| # | Gap | What the suites have | Evidence in our tree | Effect on modules |
|---|-----|----------------------|----------------------|-------------------|
| S1 | **Workflow / step-table engine** | SuiteCRM LogicHooks (loaded/saved hooks → criteria → action → chained hook); Odoo Automated Actions; Dolibarr triggers. | `WorkflowHooksTrait` fires `{prefix}_{hook}` only (before/after save/delete). **No authorable condition→action→dispatch tables, no chained DTO creation.** | Leave approvals, recruitment, quote approval, commission chains all hand-rolled per module. |
| S2 | **Approval / state-machine engine** | OrangeHRM/Odoo/Dolibarr multi-step approver chains with states + audit. | FA has page-security areas and `add_access_extensions()`; **no per-record state machine**. | Leave, quotes/purchase, expense, recruitment hiring all need "submit → approve → reject" states today. |
| S3 | **Notification + activity-feed subsystem** | All four suites notify on save/state change (email + in-app feed). | FA has `hook_invoke_all` (transport only); no feed, no per-user inbox, no email-notify discipline. | Users never learn someone approved/rejected; rework loops. |
| S4 | **Scheduled automation** | Odoo scheduled actions; SuiteCRM cron jobs; Dolibarr background tasks. | FA web-only; no cron-friendly CLI job runner pattern for modules (aside from manual `import.php`). | Recurring payroll, leave-balance accrual, quote expiry, campaign sends can't be driven. |
| S5 | **Self-service portal layer** | OrangeHRM ESS (leave/payslip/self-data); Odoo portal; Dolibarr external area. | **No portal/ESS pages** in either module. | Every request flows through an admin page; no employee/contact self-service. |
| S6 | **Document management / attachments** | SuiteCRM doc store; Dolibarr ECM; OrangeHRM PIM files. | `fa_contacts_pii.sql` holds records; **no attachment taxonomy or storage**. | No contracts, payslips, CVs, IDs as managed artifacts. |

---

## 3. HRM gaps (vs OrangeHRM, Odoo HR, Dolibarr HRM)

Current HRM state: **9 of 12 pages are CRUD-thin shells** (1,746 total lines).
`src/` has 14 Services + 14 Repositories (content exists in services), but the
page layer is largely list/edit CRUD.

| # | Gap | Current evidence | Parity target | Priority |
|---|-----|------------------|---------------|----------|
| H1 | **Recruitment = 8-line stub** (`pages/recruitment.php`) | No vacancies, applications, interviews, offers, onboarding. | OrangeHRM/Dolibarr/Odoo full ATS | High |
| H2 | **Payroll = 50-line shell** (`pages/payroll.php`) | No gross→net run, tax/social deductions, payslip, batch, GL posting; `PayrollService`/`PayrollRepository` exist but page doesn't run payroll. | Odoo/Dolibarr statutory payroll | High |
| H3 | **Leave: no approval workflow** (`pages/leave.php` 52 lines, only "balance") | No approver chain, no entitlement hazard, no absence calendar. | OrangeHRM/Dolibarr/Odoo | High |
| H4 | **No performance / appraisal** | Nothing. | OrangeHRM/Odoo | Medium |
| H5 | **No timesheet→payroll integration** | Timesheets are a separate module (`ksf_Timesheets`); not wired to HRM payroll or approvals. | OrangeHRM/Odoo attendance→payroll chain | High |
| H6 | **Onboarding/offboarding checklists** | Nothing beyond probation flags in emp page. | Odoo/Dolibarr | Medium |
| H7 | **HRM reporting = 14-line stub** (`pages/reports.php`) | No headcount, turnover, leave-balance, org-cost reports/dashboards. | OrangeHRM reports | Medium |
| H8 | **Training management (courses, certification)** | Only BR-007 `0_hrm_event_windows` evidence (attendee→worked-window). No course catalog or certification tracking. | OrangeHRM/Odoo | Medium |
| H9 | **Contracts & statutory documents as artifacts** | `fa_contacts_employment` SQL exists; no contract lifecycle or document store (see S6). | Odoo contracts | Medium |

---

## 4. CRM gaps (vs SuiteCRM, Odoo CRM, Dolibarr)

Current CRM state: 22 pages (3,976 total), ~180 avg/page. **No marketing, no
email sync, thin pipeline.**

| # | Gap | Current evidence | Parity target | Priority |
|---|-----|------------------|---------------|----------|
| C1 | **Pipeline/funnel** | `leads.php`(212) / `opportunities.php`(149): `status`-labeled CRUD only. No kanban, stage probability, weighted forecast. | SuiteCRM/Odoo funnel | High |
| C2 | **Marketing campaigns** | **No campaign page exists.** No target lists, templates, tracking. | SuiteCRM signature feature | Medium |
| C3 | **Email integration (IMAP/SMTP)** | `email_accounts.php`(314) matches only "ssl"; no imap/smtp/oauth sync, no templated sends, no canned replies. | SuiteCRM/Odoo inbox-anchored | High |
| C4 | **Quotes → order/invoice conversion** | `quotes.php`(228): 1 approve / 1 reject match, no convert-to-order flow. | Dolibarr proposal→invoice; SuiteCRM Quote→Order | High |
| C5 | **Helpdesk / cases / tickets** | **Absent.** | SuiteCRM service, Dolibarr tickets, Odoo helpdesk | Medium |
| C6 | **Contracts w/ renewal tracking** | `org_chart`/`account_relationships` exist; no contract lifecycle. | SuiteCRM/Odoo | Medium |
| C7 | **CRM dashboards / KPIs** | `dashboard.php`(153): 3 "leads" matches only. | SuiteCRM/Odoo dashboard | Medium |
| C8 | **Commercial-actions history w/ automation** | `communications.php`(201) exists; no suiteCRM-style activity chaining. | Dolibarr commercial actions; SuiteCRM activity stream | Medium |

---

## 5. Proposed BR creation order (one-by-one)

Cross-cutting substrate first (unlocks the rest), then HRM, then CRM. Each BR
follows the ProjectDcs convention (`BR-<MODULE>-<SEQ>-<short-name>`) and the
workflow BR (#1) uses the SuiteCRM-inspired step-table design below.

1. `BR-COM-01-workflow-step-table-engine` — S1 (SuiteCRM LogicHooks-inspired:
   loaded/saved hooks → DTO criteria → calculate/modify/create → fire next hook)
   ✅ DONE — incl. system-wide `get_dto` / `get_dto_list` hook (BeanFactory
   analog: any activated module's DTO is reachable + fields schema'd from the
   data dictionary), designer min-surface, chaining guard rails.
2. `BR-COM-02-approval-state-machine` — S2 ✅ DONE — SuiteCRM Advanced
   Workflow/Process-Author model: states, transitions with pre/post function
   calls, guards, user/auto/time triggers, append-only history, designer UI.
3. `BR-COM-03-notification-activity-feed` — S3 ✅ DONE — notify() publish API,
   per-user inbox, type-driven renderer, role/recipient resolution, prefs
   v1. Host-module mounting decision is folded into that BR.
4. `BR-COM-04-scheduled-automation-jobs` — S4 ✅ DONE — cron-friendly CLI
   runner + jobs registry, one-shot jobs for BR-COM-02 time-wait edges, web
   tickle fallback for no-cron environments.
5. `BR-HRM-01-leave-approval-workflow` — H3 ✅ DONE — vertical slice on
   BR-COM-02: request tables, entitlement submit guard, approver chain over
   reports_to, notifications, consume_days + calendar, security-area gates.
6. `BR-HRM-02-recruitment-ats` — H1 ✅ DONE — vacancy/application pipeline
   on BR-COM-02, job_applicant CRM reuse, interview/offer stages w/ Calendar
   link + onboarding broadcast, dedupe guard, pipeline board.
7. `BR-HRM-03-payroll-run-gross-to-net` — H2 ✅ DONE — pay-batch run,
   element engine (display_order + formula), tax/social bracket config,
   payslip view, FA GL posting + non-reversible post, BR-COM-04 schedule.
8. `BR-HRM-04-timesheet-payroll-integration` — H5 ✅ DONE — timesheet
   approval on BR-COM-02 (adapter over existing service), posted-period
   lock, OT/variable-pay element feed, approved-vs-paid reconciliation.
9. `BR-HRM-05-onboarding-offboarding-lifecycle` — H6 ✅ DONE —
   employee.lifecycle process (hired→onboarding→active→confirmed→
   separating→separated), checklist catalog+instances, RBAC/leave/payroll
   resolvers on stage entry, post-separation locks.
10. `BR-HRM-06-hrm-dashboards-reports` — H7 ✅ DONE — directory/dept/
    payroll/leave reports + CSV, turnover/headcount dashboard (single
    resolver), resolver-per-dataset.
11. `BR-HRM-07-training-certification-catalog` — H8 ✅ DONE — course
    catalog, enrollment process over BR-007 windows, certificate issue +
    expiry job + reminders.
12. `BR-HRM-08-employee-self-service-portal` — H9+S5 ✅ DONE — ESS shell,
    MY_* areas, my-leave/payslips/inbox/checklist/certs, server-enforced
    owner scoping contract.
13. `BR-CRM-01-crm-pipeline-funnel-forecast` — C1 ✅ DONE — pipeline config
    (stage→probability single source), kanban view, weighted forecast
    resolver, won/lost guards, lead→opp conversion wiring.
14. `BR-CRM-02-email-integration-imap-smtp` — C3 ✅ DONE — mailbox adapter
    (IMAP w/ graceful no-ext), import worker, SMTP send, mail→record
    mapping, templates + canned replies, routing rules (assign/ticket).
15. `BR-CRM-03-quote-to-order-conversion` — C4 ✅ DONE — quote lifecycle
    process, accept→FA sales-order conversion (idempotent, row-gap report),
    expiry job, opportunity-won linkage.
16. `BR-CRM-04-marketing-campaigns` — C2 ✅ DONE — campaign entity, target
    list snapshots, send dispatcher, open/click tracking, campaign→lead→
    opp attribution.
17. `BR-CRM-05-helpdesk-tickets` — C5 ✅ DONE — ticket entity + lifecycle,
    mail-rule-born tickets, routing, canned replies, SLA due + remind job.
18. `BR-CRM-06-contracts-renewal-lifecycle` — C6 ✅ DONE — contract entity,
    lifecycle, append-only renewal chain + auto-renew, reminders, C7 block.
19. `BR-CRM-07-crm-dashboards-kpis` — C7 ✅ DONE — widget registry +
    resolver-driven cards/charts (pipeline, SLA, contracts, conversion,
    velocity), owner filter, deep links.
20. `BR-CRM-08-activity-automation-chaining` — C8 ✅ DONE — event surface
    onto BR-COM-01 (replacing crm_dispatch_event no-op), seed step rows,
    follow-up automation, append-only activity ledger.

✅ **ALL 20 GAPS CLOSED (BR ratified).** Next phase = implement the substrate
(S1–S4) so the module BRs can land on real engines.

---

## 6. SuiteCRM-style workflow step-table (design seed for BR-COM-01)

The authoring format shared by workflow-capable BRs (reference the user's
SuiteCRM inspiration):

> When a **record is loaded or saved** hooks are fired. Based on **criteria in
> the DTO** (e.g. field X set), **calculations are run**, the **DTO modified
> or another DTO created**, and **hooks fired again** (chaining).

| Step | Event/Hook | Criteria (IF, on DTO) | Then: calculation / function call | Result handling (DO) | Else |
|------|-----------|----------------------|------------------------------------|----------------------|------|
| e.g. | `after_save` order | `status == 'shipped'` | `commission = percent(amount, rate)` | set `order.commission_amount`; create `CommissionEntry` DTO → fire `after_save` (chain) | no-op |

Full design: see `BR-COM-01-workflow-step-table-engine`.

---

## 7. Closure RTM (traceability: gap → requirement → code)

As each gap's **code** is completed, the roadmap row is updated here with an
RTM entry pointing at the requirement (FR) and the classes that close the gap.
Paths are **relative to the owning module root** (e.g. `src/...`, `pages/...`);
FR references are the ProjectDcs requirement files. This table is the 
machine-checkable closure ledger — the RTM generator (AGENTS.md tracing) can
consume it at release prep.

| Gap | Requirement (FR/BR ref) | Implementing classes (module-relative) | Tests (module-relative) | Status |
|-----|--------------------------|-----------------------------------------|--------------------------|--------|
| S1  | BR-COM-01 (FR-COM-01-006) | ksf_FA_Common `src/Workflow/…`, `src/Contract/DtoProviderInterface.php` | — | BR ratified |
| S2  | BR-COM-02 (FR-COM-02-003) | ksf_FA_Common `src/Workflow/…` (state engine) | — | BR ratified |
| S3  | BR-COM-03 (FR-COM-03-001) | ksf_FA_Common `src/Notification/…` (+ host module mount) | — | BR ratified |
| S4  | BR-COM-04 (FR-COM-04-002) | ksf_FA_Common `src/Scheduler/…` (+ cron entry) | — | BR ratified |
| H3  | BR-HRM-01 (FR-HRM-001-001) | ksf_FA_HRM `src/Leave/…`, `pages/leave*.php`, `sql/0_hrm_leave_request*.sql` | — | BR ratified |
| H1  | BR-HRM-02 (FR-HRM-002-001) | ksf_FA_HRM `src/Recruitment/…`, `pages/recruitment*.php`, `sql/0_hrm_vacanc*.sql` | — | BR ratified |
| H2  | BR-HRM-03 (FR-HRM-003-001) | ksf_FA_HRM `src/Payroll/…`, `pages/payroll*.php`, `sql/0_hrm_pay_batches.sql` | — | BR ratified |
| H5  | BR-HRM-04 (FR-HRM-004-003) | ksf_FA_HRM `src/TimesheetLink/…`, ksf_Timesheets entity/service | — | BR ratified |
| H6  | BR-HRM-05 (FR-HRM-005-001) | ksf_FA_HRM `src/Lifecycle/…`, `sql/0_hrm_checklist*.sql` | — | BR ratified |
| H7  | BR-HRM-06 (FR-HRM-006-001) | ksf_FA_HRM `src/Reporting/…`, `pages/reports.php` | — | BR ratified |
| H8  | BR-HRM-07 (FR-HRM-007-001) | ksf_FA_HRM `src/Training/…`, `sql/0_hrm_courses.sql` | — | BR ratified |
| H9+S5 | BR-HRM-08 (FR-HRM-008-008) | ksf_FA_HRM `src/SelfService/…`, `ess/*.php` | — | BR ratified |
| C1  | BR-CRM-01 (FR-CRM-001-003) | ksf_FA_CRM `src/Pipeline/…`, `pages/opportunities.php` | — | BR ratified |
| C3  | BR-CRM-02 (FR-CRM-002-001) | ksf_FA_CRM `src/Mailbox/…`, `sql/0_fa_crm_mail_*.sql` | — | BR ratified |
| C4  | BR-CRM-03 (FR-CRM-003-002) | ksf_FA_CRM `src/Quote/…`, `pages/quotes.php` | — | BR ratified |
| C2  | BR-CRM-04 (FR-CRM-004-001) | ksf_FA_CRM `src/Campaign/…`, `sql/0_fa_crm_campaigns.sql` | — | BR ratified |
| C5  | BR-CRM-05 (FR-CRM-005-001) | ksf_FA_CRM `src/Helpdesk/…`, `sql/0_fa_crm_tickets.sql` | — | BR ratified |
| C6  | BR-CRM-06 (FR-CRM-006-001) | ksf_FA_CRM `src/Contract/…`, `sql/0_fa_crm_contracts.sql` | — | BR ratified |
| C7  | BR-CRM-07 (FR-CRM-007-001) | ksf_FA_CRM `src/Dashboard/…`, `pages/dashboard.php` | — | BR ratified |
| C8  | BR-CRM-08 (FR-CRM-008-001) | ksf_FA_CRM `src/Automation/…`, `includes/crm_db.inc` | — | BR ratified |

Rule: a status becomes "implemented" only when the class paths and their tests
are real files in the module tree (verified at release prep, not by intent).