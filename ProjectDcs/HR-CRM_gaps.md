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
5. `BR-HRM-01-leave-approval-workflow` — H3 (+S2)
6. `BR-HRM-02-recruitment-ats` — H1
7. `BR-HRM-03-payroll-run-gross-to-net` — H2
8. `BR-HRM-04-timesheet-payroll-integration` — H5
9. `BR-HRM-05-onboarding-offboarding-lifecycle` — H6
10. `BR-HRM-06-hrm-dashboards-reports` — H7
11. `BR-HRM-07-training-certification-catalog` — H8
12. `BR-HRM-08-employee-self-service-portal` — H9+S5
13. `BR-CRM-01-crm-pipeline-funnel-forecast` — C1
14. `BR-CRM-02-email-integration-imap-smtp` — C3
15. `BR-CRM-03-quote-to-order-conversion` — C4
16. `BR-CRM-04-marketing-campaigns` — C2
17. `BR-CRM-05-helpdesk-tickets` — C5
18. `BR-CRM-06-contracts-renewal-lifecycle` — C6
19. `BR-CRM-07-crm-dashboards-kpis` — C7
20. `BR-CRM-08-activity-automation-chaining` — C8

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
| S1  | BR-COM-01 (FR-COM-01-00X) | ksf_FA_Common `src/…` (pending implementation) | — | BR ratified |
| S2  | BR-COM-02 (FR-COM-02-00X) | ksf_FA_Common `src/…` (pending implementation) | — | BR ratified |
| S3  | BR-COM-03 (FR-COM-03-00X) | ksf_FA_Common `src/…` (+ host module mount) | — | BR ratified |
| S4  | BR-COM-04 (FR-COM-04-00X) | ksf_FA_Common `src/…` (+ cron entry) | — | BR ratified |
| H1…H9, C1…C8 | FR-HRM-0XX / FR-CRM-0XX (per §5 BRs) | filled when implemented | — | pending |

Rule: a status becomes "implemented" only when the class paths and their tests
are real files in the module tree (verified at release prep, not by intent).