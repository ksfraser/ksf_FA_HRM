# BR-HRM-06 — HRM Dashboards & Reports (H7)

**Modules:** ksf_FA_HRM, substrate BR-COM-01 (get_dto schema), BR-COM-04
(refresh jobs)
**Status:** PENDING (design ratified in this BR)
**Built on:** `pages/reports.php` is the 14-line stub (5 listed reports, no
output), existing services/repos (Employee, Payroll, Leave/balance, Benefit,
OrgHierarchy), BR-COM-01 `get_dto` for consistent field schemas.

## Business Need / Current State (verbatim from tree)

`pages/reports.php` is a **14-line stub**: an unordered list naming Employee
Directory, Department Summary, Payroll Summary, Benefits Summary, Leave
Balance Report — with **no queries, no outputs**. The gaps doc also flags the
only-dashboard `CRM_num_*` counters as thin (CRM dashboard is the same 3
"leads/opportunity/customers" counts pattern). H7 needs actual reporting:
headcount, turnover, leave-balance, org-cost, dashboards at a glance.

## Business Requirement

The business requires:

1. **Employee directory** — filters (dept/team/grade/active/status); columns
   name, hire_date, position, grade, dept, team, report-to, status; CSV
   export.
2. **Department summary** — headcount, vacant positions (hrm_positions no
   active incumbent), average grade, cost-of-role (salary structure sum).
3. **Payroll summary** — per batch/period/gross/tax/deductions/net ✓ and
   per-period comparative (previous period delta). Reads BR-HRM-03 rows; no
   recompute.
4. **Leave balance + usage report** — per person per type: entitlement,
   used, balance, upcoming requests (BR-HRM-01 tables); per-dept apron view.
5. **Headcount / turnover KPI dashboard** — hires, leavers, net change,
   voluntary-turnover % over period; headcount by dept pie, projected growth
   (from open vacancies). Turnover derived from `separated` + separation
   reason rows (BR-HRM-05).
6. **Org-cost and cost-per-employee** — salary structure totals by
   position/grade/dept (BR-HRM-03 data).
7. **Data-safety** — reports share the same schema/labels as `get_dto`
   (BR-COM-01) so field names in the UI are the dictionary labels; all
   aggregates are computed SQL via FA `db_*`, no page-loop math for big sets
   (a report resolver per dataset, driven by a `get_dto_list` criteria
   contract).
8. **Refresh** — heavyweight dashboards (turnover, cost) computed live on
   demand (parameter: period); no background materialization needed in v1
   (small datasets) — an optional BR-COM-04 cache job is a follow-on.

## Scope

- In scope: the 5 named reports (real output + CSV), a summary dashboard
  page (cards + turnover chart), report router page, resolvers, filtering,
  UAT.
- Out of scope: visual charting library (simple CSS/HTML bars + FA tables in
  v1), scheduled PDF digests (BR-COM-04 follow-on), multi-currency cost.

## Design

### Report page (replaces the stub)

- Single `reports.php` router (`?report=directory|department|payroll|
  leave|headcount`), each section = a **resolver** returning rows via
  `get_dto_list`-shaped criteria (module filter fields are dictionary
  schema), rendered as FA `start_table` + CSV export link (`&csv=1` adds
  download headers).
- Dashboard sub-block: cards (headcount, open vacancies, leavers-this-period,
  resigned-turnover %) + dept headcount bars — computed per request from the
  lifecycle/employment tables.

### Turnover math (single definition — the "shared performance math" pattern)

```
headcount(period) = active employees at period end
hires(period)     = entered 'active' in period (BR-HRM-05 confirmations)
leavers(period)   = entered 'separated' in period
net change        = hires - leavers
turnover %        = leavers(voluntary) / ((headcount(start)+headcount(end))/2)
```

Stored as a resolver (CalcRegistry), shared by dashboard + report + future
BR-HRM-08 portal KPI — defined once, SRP/DRY. Internally one pass.

## Supporting FRs

- FR-HRM-006-001 reports router + per-report resolvers (mirror get_dto_list)
- FR-HRM-006-002 employee directory + filters + CSV
- FR-HRM-006-003 department summary (headcount/vacancy/cost/grades)
- FR-HRM-006-004 payroll summary (per batch + prior-period delta)
- FR-HRM-006-005 leave balance + usage report
- FR-HRM-006-006 headcount/turnover dashboard (cards + turnover resolver)
- FR-HRM-006-007 unit/UAT (resolver-level assertions on seeded data)

## Acceptance Criteria (UAT)

1. Unit: each resolver returns correct rows for seeded data (directory filter
   by dept; turnover = expected given seeded hires/leavers; payroll totals
   match batch rows).
2. Unit: CSV export emits headers + rows (byte-identical to visible table).
3. e2e (live FA container, `e2e_hrm_event_windows.php` mirror): seed 2 depts,
   3 persons (one separated) → dashboard shows headcount 2, leavers 1,
   turnover correct; directory CSV downloads.

## Non-Functional

- PHP 7.3; PSR-4 `HRM\Reporting\`; all aggregates SQL-through-`db_*`;
  resolver-per-dataset (no inline page queries); FA table/renderer only.
- Deterministic date handling (period params), no timezone math in page.

## Related

- BR-HRM-01/03/05 (leave balance, payroll, turnover source data), BR-COM-01
  (`get_dto_list` criteria + labels), C7 (CRM dashboard follows same pattern)
- H7 gap; feeds H8 portal KPIs. Optional BR-COM-04 cache job later.