# BR-HRM-010 — Single Front Controller & Filter/List/Form Tab Consolidation

**Modules:** `ksf_FA_HRM`
**Shared dependency:** `ksfraser/ksf-fa-common` ≥ **v1.0.13** (`AbstractFrontController`); **v1.0.14** for the `loadUiIncludes()` fix.
**Status:** Approved and **implemented** — commits `709bd94` (page rebuild), `ecbeff6` (controller consolidation), `5906c1c` (tab selector rename), `18fcba5` (adopt `AbstractFrontController`).
**Source of record:** `ksf_Architecture_Docs/HRM_Page_Rebuild.md`,
`ksf_Architecture_Docs/HRM_Controller_Consolidation.md`.
**Date:** 2026-10-13

---

## Business Need (BABOK What)

HRM is the module that most clearly needs one application surface rather than a
directory of standalone pages. Before this BR the module shipped twelve
`pages/*.php` scripts plus an `index.php` router, each hand-rolling the same
preamble: read `?view=`, decide a security area, include `session.inc`, render a
menu, dispatch. Four of those scripts had drifted badly enough to be broken:

- `pages/leave.php` queried a table and columns that do not exist.
- `pages/recruitment.php` renders a full entry form for a data model the module
  does not ship, so a save was silently accepted and not stored.
- `pages/reports.php` was a 14-line stub — five report names and no output.
- `pages/payroll.php` referenced a class it never imported.

The business consequence is not aesthetic. An HR user cannot tell which screen
they are on, the same entity is edited through three different control flows, and
a refused write is invisible. HRM also had a genuine access-control gap: an
`action=` POST took a path each hand-rolled controller had to remember to check.

## Business Requirement

HRM shall present the whole module through **one** front controller
(`hrm.php`) serving **twelve** tabs, and every tab shall render the same
three-section pattern — **filter bar → summary table → entry form** — with a
single, non-bypassable access check per request.

Concretely:

1. **One entry point.** `hrm.php` is the only route. The former `pages/*.php`
   scripts survive as thin forwarder shims so old bookmarks and links keep
   working.
2. **Twelve tabs, each controller-backed.** employees, departments, positions,
   grades, roles, teams, payroll, benefits, leave, leave_types, recruitment,
   reports. No `page_file` fallback remains in `HrmAppShell::registerCoreTabs()`.
3. **Consistent layout.** Every tab renders the filter → summary → form stack.
   The form is always on the page; there are no "Add XYZ" links.
4. **One access check.** Access is enforced exactly once per request, before the
   mutation path is chosen, so an `action=` POST cannot route around it.
5. **Per-tab security from the tab's own area**, resolved before `session.inc`
   where possible and re-resolved after third-party tabs register.
6. **Honest failure.** Where the module has no data model (recruitment), the tab
   says so and refuses writes rather than pretending to persist them.

## Scope boundary — what this BR explicitly does NOT deliver

- **Recruitment stays structure-only.** The module ships no
  vacancy/candidate/application table. No schema is invented here.
- **Leave *requests* are not on the leave tab.** That tab is leave *balances*
  (`FR-HRM-001-005`). The request workflow is `BR-HRM-01`.
- **No CSRF token.** Not introduced by this BR; see the known-gap note in
  FR-HRM-010-001.

## Acceptance

| ID | Criterion |
|---|---|
| ARI-1 | All twelve tabs render at `hrm.php?tab=<tab>` with a complete footer and no fatal. |
| ARI-2 | The four consolidated tabs keep filter section + summary table + entry form. |
| ARI-3 | The four former `pages/*.php` URLs still return HTTP 200 with full content. |
| ARI-4 | `?tab=99` (bogus) falls back to `employees`, HTTP 200, no error. |
| ARI-5 | `?page=<n>` is NEVER read as a tab selector. |
| ARI-6 | `?action=delete` over GET is refused; the verb is POST-only. |
| ARI-7 | A user holding a tab's area but not `SA_ksf_FA_HRMVIEW` is NOT refused at `session.inc`. |
| ARI-8 | A tab contributed through `hrm_register_tabs` is reachable AND checked against **its own** area. |
| ARI-9 | Output is byte-identical before vs after the `AbstractFrontController` adoption across the 26-route probe set. |
| ARI-10 | `hooks.php` is byte-identical to `HEAD` across all four commits. |

## Traceability

- FR-HRM-010-001 — app shell front controller
- FR-HRM-010-002 — tab controller page pattern (filter → summary → form)
- Implemented by: `hrm.php`, `src/App/HrmFrontController.php`,
  `src/App/HrmAppShell.php`, `src/Controller/*TabController.php`,
  `src/Controller/FilteredTabController.php`, `src/Controller/TabActionRouting.php`
- Tests: `tests/Unit/HrmFrontControllerTest.php` (32 cases)