# FR-HRM-010-002 — HRM tab controller page pattern (filter → summary → form)

@BABOK Related: BR-HRM-010; FR-006-007 (field metadata schema);
          FR-HRM-001-002 (payroll tab), FR-HRM-001-005 (leave balances).
@UML : APP_TAB_ARCHITECTURE.md §11 (tab-controller SRP); the reference
       renderers are `ksfraser\Frontaccounting\HTML\MasterSummaryTable`,
       `FormFooter`, `TabContext` and `ksfraser\HTML\Crud\FieldForm`.
Status: Approved — BABOK; **implemented**, commit `709bd94` (pages rebuilt on the
       pattern) and `ecbeff6` (pages consolidated behind `hrm.php`).
Module: ksf_FA_HRM.

> **Traceability caveat.** `FilteredTabController.php` carries
> `@BABOK Related: FR-HRM-001-002 (payroll), FR-HRM-001-005 (leave balances)`.
> Those sub-numbers are the **leave-approval workflow** functions, not the tabs;
> this document is the tab/UI requirement they should point at. Not corrected
> here — documentation-only task.

---

## Need (BABOK What-not-How)

HRM's four data-heavy screens (`leave`, `payroll`, `recruitment`, `reports`) were
four procedural page scripts with four different control flows, and three of them
were broken (§5 of `HRM_Page_Rebuild.md`). Users could not predict where the Edit
button would take them, and a save that failed validation left no message.

The pattern is not new. It is the one `ksf_FA_CRM` already used
(`pages/leads.php`, `ContactsTabController`) and the one core FA list pages use
(`admin/fiscalyears.php`).

## Requirement

1. **Every HRM tab renders three sections, top to bottom, inside ONE
   `<form>`:**
   1. **Filter** — `start_table(TABLESTYLE2)` + `table_section_title(_('Filter'))`
      with `array_selector_row()` / `text_row()` and a Filter submit.
   2. **Summary table** — `start_table(TABLESTYLE)` + `table_header()`,
      `label_cell()` / `qty_cell()` / `amount_cell()`, and per-row
      `edit_button_cell()` / `delete_button_cell()` with `submit_js_confirm()` on
      destructive actions.
   3. **Entry form** — `start_table(TABLESTYLE2)` +
      `submit_add_or_update_row()` / `submit_add_or_update_center()`.

   **Zero Bootstrap classes. No inline `<div>`/`<table>` HTML.**
2. **The form is always on the page.** There is no "Add XYZ" link. Blank for
   add; `Edit` on a summary row pre-loads the form and the footer flips from
   `Save` to `Update`.
3. **One form spans all three sections**, so the filter survives an edit, a save
   or a delete with no URL plumbing.
4. **`reports.php` has no third section by design** — it is read-only, so the
   third slot carries the "what these figures include / do not include" notes
   that replaced a static "coming soon" list.
5. **Shared package untouched.** `AbstractTabController` (ksf-fa-common) has no
   slot for a filter bar and its default entry form delegates to `FieldForm`,
   which emits Bootstrap markup. HRM adds a **local** intermediate subclass
   `src/Controller/FilteredTabController.php` that inserts the filter section and
   re-renders the control grid with FA's own `ui_input.inc` row helpers. The
   shared package remains the contract for all twelve tabs.
6. **Mutation routing is a trait, not controller logic.** `createRecord()` and
   friends are `protected`, so the host page cannot reach them.
   `src/Controller/TabActionRouting.php` supplies `runAction()` once and is
   `use`d by every filtered controller, so the verb vocabulary is identical
   everywhere and there is a single write path:

   | Verb | Method | Notes |
   |---|---|---|
   | `action=save` | POST | create or update, from `<pk>_submit` / the form footer |
   | `action=delete` | POST | delete the record named by `id`. **POST-only on purpose** — a GET-addressable delete is a CSRF vector, which is why the summary table's Delete buttons are POST submit buttons. |
   | `action=edit` | GET | redirect back with the pk set, pre-loading the form |

   Every one of those ends in the Service / Repository layer. `FilteredTabController`
   and its subclasses contain **no SQL**.
7. **Refusals re-render, they do not redirect.** When a service rejects a payload
   (validation error, a leave balance that cannot be released), the controller sets
   `skipRedirect`; `finishAction()` then re-renders the tab rather than
   PRG-redirecting away from the message, and the rejected POST values are
   re-injected into the entry form so the user corrects rather than retypes.
   Payroll overrides the hook to re-derive net pay — otherwise a refused save
   would display a net figure different from the one that was refused.
8. **Where there is no data model, say so.** The recruitment tab renders its full
   filter / summary / entry-form structure and refuses every write with an
   explicit message, instead of silently accepting an entry that is not stored.

## Security areas per tab

Read from `HrmAppShell::registerCoreTabs()`; each is declared in
`hooks_ksf_FA_HRM::install_access()` (`ksf_FA_HRM/hooks.php:39-78`). `hooks.php`
was **not modified** by any of this work.

| Tab | `$page_security` |
|---|---|
| employees | `SA_HRM_EMPLOYEE` |
| departments | `SA_HRM_DEPARTMENT` |
| positions | `SA_ksf_FA_HRMMANAGE` |
| grades | `SA_ksf_FA_HRMMANAGE` |
| roles | `SA_HRM_ROLES` |
| teams | `SA_HRM_TEAMS` |
| payroll | `SA_HRM_PAYROLL` |
| benefits | `SA_HRM_BENEFITS` |
| leave | `SA_HRM_LEAVE` |
| leave_types | `SA_HRM_LEAVE` |
| recruitment | `SA_HRM_RECRUITMENT` |
| reports | `SA_ksf_FA_HRMVIEW` |

`reports` deliberately uses the module-wide read-only area because the reports
expose company-wide headcount and pay aggregates.

## Paging

`MasterSummaryTable` copies the `$_GET` keys named in `preserve_params` verbatim
onto the pager links it builds. HRM's controllers return `['tab']`, so links
read `?page=3&tab=leave`. **A third-party tab on HRM must override
`preserveParams()` the same way** or its pager will drop the tab — see
`How_To_Add_Tabs_To_A_Module.md` §5.

## Acceptance

- ARI-2 → the four consolidated tabs show filter section + summary table + entry form; reports keeps its notes block.
- The four former `pages/*.php` URLs return HTTP 200 with full content.
- `?page=2&tab=departments` resolves to the departments tab.
- `action=edit&id=N` (GET) → `302` → `hrm.php?tab=departments&department_id=3`.
- `action=save` (POST, valid) → `302` → `hrm.php?tab=payroll&filter_status=Draft`; record created.
- `action=delete` (GET) → refused: `Delete must be submitted as a POST.`
- A refused `action=save` leaves the entry form populated with the rejected values and shows the message.
- Editing a leave balance and saving replaces the allocation rather than adding to it.
- Recruitment refuses every write with an explicit message.

## Business-logic defects found and fixed by this work

Recorded because they were pre-existing and are easy to reintroduce:

| # | Defect | Resolution |
|---|---|---|
| 5.1 | `pages/leave.php` queried a table/columns that do not exist | fixed |
| 5.2 | `upsertBalance()` could not execute at all | fixed (pre-existing) |
| 5.3 | `0_leave_types` does not exist | made resilient, **not** created |
| 5.4 | `PayrollService` referenced an unimported class | fixed (pre-existing) |
| 5.5 | `date_row()` silently corrupts optional date filters | fixed |
| 5.6 | `index.php` never included `includes/ui.inc` | fixed |
| 5.7 | Pages are dual-use (routed **and** reached directly) | handled — direct URL and app-shell route enforce identical access |
| — | Editing a leave balance **doubled** the days (`allocate()` is additive by design; the old page called it for both ADD and UPDATE) | fixed — `updateRecord()` passes the *difference* against the stored allocation |

## Known gaps

- **Not authenticated end-to-end in a browser** (`HRM_Page_Rebuild.md` §6).
- **Recruitment has no data model** — no schema invented.
- **Leave requests are not on the leave tab** — that tab is leave balances.
- **No CSRF token** anywhere in the pattern; `action=delete` is POST-only as the
  compensating control.