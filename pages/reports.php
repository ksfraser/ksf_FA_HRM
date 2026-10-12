<?php
/**
 * HRM — Reports.
 *
 * Layout follows the CRM pattern: filter controls at the top, the summary
 * table beneath them. Reports are read-only, so there is no data-entry form
 * on this page by design; the third slot carries the report definition notes
 * that used to be a static "coming soon" list.
 *
 *   1. Filter  — report, reporting year, department.
 *   2. List    — the selected report's rows.
 *   3. Notes   — what the figures do and do not include.
 *
 * The page is thin: every aggregate comes from HrmReportService. No SQL lives
 * here.
 *
 * Security area: SA_ksf_FA_HRMVIEW — defined by
 * hooks_ksf_FA_HRM::install_access() in ../hooks.php
 * (SS_ksf_FA_HRM | 1, "View HRM"). Reporting exposes headcount and pay data
 * across the company, so the read-only module-wide area is used rather than
 * the narrower per-function areas.
 *
 * Dual use: reachable directly by URL, and also *included* by ../index.php
 * when HrmAppShell dispatches the reports tab.
 *
 * PHP 7.3 compatible.
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §10 (page -> service -> repository)
 * @BABOK Related: FR-HRM-001 (HRM reporting)
 */

$hrm_dispatched = defined('HRM_APP_SHELL_DISPATCH');

if (!$hrm_dispatched) {
    $page_security = 'SA_ksf_FA_HRMVIEW';
    $path_to_root = '../../..';

    include_once($path_to_root . '/includes/session.inc');
    add_access_extensions();

    require_once __DIR__ . '/../ComposerDependencies.php';
    \ksfraser\FrontAccounting\HRM\Utils\ComposerDependencies::ensure(__DIR__ . '/..');
}

// FA's bootstrap loads only ui_view/ui_controls/ui_msgs; ui_input.inc and
// ui_lists.inc must be included by the page itself, in BOTH the standalone and
// the app-shell-dispatched case. Include it after the guard so a directly
// requested page and a shell-dispatched page are loaded identically.
include_once($path_to_root . '/includes/ui.inc');

require_once __DIR__ . '/../vendor/autoload.php';

use ksfraser\FrontAccounting\HRM\Service\HrmReportService;

$reports = new HrmReportService();

if (!$hrm_dispatched) {
    page(_($help_context = 'HRM Reports'), false, false, '', '');
}

$report_titles = $reports->reportTitles();
$report_keys = array_keys($report_titles);

$report_key = (string) get_post('filter_report', '');
if (!isset($report_titles[$report_key])) {
    $report_key = $report_keys[0];
}

$year = (int) get_post('filter_year', 0);
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}

$dept_id = (int) get_post('filter_department_id', 0);

$report = $reports->run($report_key, $year, $dept_id);

start_form();

// ----------------------------------------------------------------------------
// 1. Filter bar
// ----------------------------------------------------------------------------

start_table(TABLESTYLE2, 'width=95%');
table_section_title(_('Filter'));

array_selector_row(_('Report:'), 'filter_report', $report_key, $report_titles);
text_row(_('Reporting Year:'), 'filter_year', (string) $year, 4, 4);
array_selector_row(_('Department:'), 'filter_department_id', $dept_id,
    $reports->departmentOptions(), ['spec_option' => _('All departments')]);

submit_row('Filter', _('Run Report'), false, '', false);
end_table(1);

// ----------------------------------------------------------------------------
// 2. Summary table
// ----------------------------------------------------------------------------

start_table(TABLESTYLE, 'width=95%');
table_section_title($reports->reportTitle($report_key));
table_header($report['columns']);

foreach ($report['rows'] as $row) {
    $is_total = isset($row['employee']) && $row['employee'] === 'TOTAL';

    start_row($is_total ? "class='currentfg'" : '');

    foreach ($report['columns'] as $index => $column) {
        $keys = array_keys($row);
        $field = isset($keys[$index]) ? $keys[$index] : '';
        $value = $field !== '' ? $row[$field] : '';

        // Money columns are the numeric ones after the leading label columns.
        if ($index > 0 && is_numeric($value)) {
            if (in_array($column, ['Gross', 'Deductions', 'Net'], true)) {
                amount_cell((float) $value, $is_total);
            } else {
                qty_cell($value, $is_total, 2);
            }
        } else {
            label_cell($value === null ? '' : (string) $value, $is_total ? 'class=currentfg' : '');
        }
    }

    end_row();
}

if (count($report['rows']) === 0) {
    start_row();
    label_cell(_('This report has no rows for the selected filters.'), 'colspan=' . count($report['columns']));
    end_row();
}

end_table(1);

end_form();

// ----------------------------------------------------------------------------
// 3. Report notes (read-only page: no data-entry form)
// ----------------------------------------------------------------------------

start_table(TABLESTYLE2, 'width=95%');
table_section_title(_('About These Reports'));
text_row(_('Employee Headcount:'), 'note_headcount',
    'Counts active CRM persons, optionally narrowed to one department.', 0, 0);
text_row(_('Department Summary:'), 'note_departments',
    'Active departments with the number of active employees attached to each.', 0, 0);
text_row(_('Payroll Summary:'), 'note_payroll',
    'Per-employee totals over payroll runs whose period starts inside the selected '
    . 'year, with a grand total row. Only run headers are counted; the individual '
    . 'earnings and deduction lines are not reconciled here.', 0, 0);
text_row(_('Benefits Summary:'), 'note_benefits',
    'Active benefit catalogue entries with the number of employee enrolments '
    . 'recorded against each.', 0, 0);
text_row(_('Leave Balance Report:'), 'note_leave',
    'Per-employee leave allocation for the selected year: allocated, carried over, '
    . 'used, and the remaining balance.', 0, 0);
end_table(1);

display_note(_('Leave type names are resolved from the leave type master when the '
    . 'module that owns it is installed; on a standalone ksf_FA_HRM install the '
    . 'numeric type id is shown instead.'), 0);

if (!$hrm_dispatched) {
    end_page();
}