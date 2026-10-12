<?php
/**
 * HRM — Payroll.
 *
 * Layout follows the CRM pattern: filter controls at the top, the list table
 * beneath them, then the data-entry form.
 *
 *   1. Filter  — employee, status, pay period overlap, plus Filter / Clear.
 *   2. List    — payroll runs with gross / deductions / net per period.
 *   3. Form    — create a payroll run, or edit an existing one.
 *
 * The page is thin: reads go through PayrollService, writes go through
 * PayrollService::createRun() / updateRun(). No SQL lives here.
 *
 * Security area: SA_HRM_PAYROLL — defined by hooks_ksf_FA_HRM::install_access()
 * in ../hooks.php (SS_ksf_FA_HRM | 5, "Payroll").
 *
 * Dual use: reachable directly by URL, and also *included* by ../index.php
 * when HrmAppShell dispatches the payroll tab. When included, FA is already
 * booted and page() has already been called, so only the body is rendered.
 *
 * PHP 7.3 compatible.
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §10 (page -> service -> repository)
 * @BABOK Related: FR-HRM-001-002 (payroll)
 */

$hrm_dispatched = defined('HRM_APP_SHELL_DISPATCH');

if (!$hrm_dispatched) {
    $page_security = 'SA_HRM_PAYROLL';
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

use ksfraser\FrontAccounting\HRM\Service\PayrollService;

$service = new PayrollService();

$js = '';
if (function_exists('user_use_date_picker') && user_use_date_picker()) {
    $js .= get_js_date_picker();
}

if (!$hrm_dispatched) {
    page(_($help_context = 'Payroll'), false, false, '', $js);
}

simple_page_mode(true);

$filters = [
    'person_id' => (int) get_post('filter_person_id', 0),
    'status' => (string) get_post('filter_status', ''),
    'from_date' => (string) get_post('filter_from_date', ''),
    'to_date' => (string) get_post('filter_to_date', ''),
];

$person_options = $service->personOptions();
$status_options = $service->statusOptions();
if (count($status_options) === 0) {
    // Nothing run yet: keep the combo usable for the first entry.
    $status_options = ['Draft' => 'Draft', 'Approved' => 'Approved', 'Paid' => 'Paid'];
}

$form_values = [
    'person_id' => '',
    'pay_period_start' => '',
    'pay_period_end' => '',
    'gross_pay' => '',
    'total_deductions' => '',
    'net_pay' => '',
    'pay_date' => '',
    'status' => 'Draft',
];
$editing = false;

// ----------------------------------------------------------------------------
// POST handling — no SQL here
// ----------------------------------------------------------------------------

if ($Mode === 'ADD_ITEM' || $Mode === 'UPDATE_ITEM') {
    $posted = [
        'person_id' => get_post('person_id', 0),
        'pay_period_start' => get_post('pay_period_start', ''),
        'pay_period_end' => get_post('pay_period_end', ''),
        'gross_pay' => get_post('gross_pay', 0),
        'total_deductions' => get_post('total_deductions', 0),
        // Net pay is derived, not trusted from the browser: the client value
        // is only a convenience preview of the same arithmetic.
        'net_pay' => ((float) get_post('gross_pay', 0)) - ((float) get_post('total_deductions', 0)),
        'pay_date' => get_post('pay_date', ''),
        'status' => get_post('status', 'Draft'),
    ];

    if ($Mode === 'UPDATE_ITEM' && $selected_id > 0) {
        $result = $service->updateRun($selected_id, $posted);
    } else {
        $result = $service->createRun($posted);
    }

    if ($result['ok']) {
        display_notification(_('Payroll run has been saved'));
        $Mode = 'RESET';
    } else {
        display_error($result['error']);
        $form_values = [
            'person_id' => (string) $posted['person_id'],
            'pay_period_start' => (string) $posted['pay_period_start'],
            'pay_period_end' => (string) $posted['pay_period_end'],
            'gross_pay' => (string) $posted['gross_pay'],
            'total_deductions' => (string) $posted['total_deductions'],
            'net_pay' => (string) $posted['net_pay'],
            'pay_date' => (string) $posted['pay_date'],
            'status' => (string) $posted['status'],
        ];
        // Keep the filter applied while the user corrects a rejected entry.
        $_POST['filter_person_id'] = (string) $filters['person_id'];
        $_POST['filter_status'] = $filters['status'];
        $_POST['filter_from_date'] = $filters['from_date'];
        $_POST['filter_to_date'] = $filters['to_date'];
    }
}

if ($Mode === 'Delete') {
    if ($selected_id > 0) {
        $service->delete($selected_id);
        display_notification(_('Payroll run has been deleted'));
    }
    $Mode = 'RESET';
}

if ($Mode === 'Edit') {
    $row = $service->getById($selected_id);
    if (!empty($row)) {
        $form_values = [
            'person_id' => (string) $row['person_id'],
            'pay_period_start' => (string) $row['pay_period_start'],
            'pay_period_end' => (string) $row['pay_period_end'],
            'gross_pay' => (string) $row['gross_pay'],
            'total_deductions' => (string) $row['total_deductions'],
            'net_pay' => (string) $row['net_pay'],
            'pay_date' => (string) $row['pay_date'],
            'status' => (string) $row['status'],
        ];
        $editing = true;
    } else {
        display_error(_('The selected payroll run could not be found.'));
    }
}

$payrolls = $service->search($filters);

start_form();

// ----------------------------------------------------------------------------
// 1. Filter bar
// ----------------------------------------------------------------------------

start_table(TABLESTYLE2, 'width=95%');
table_section_title(_('Filter'));

array_selector_row(_('Employee:'), 'filter_person_id', $filters['person_id'],
    $person_options, ['spec_option' => _('All employees')]);
array_selector_row(_('Status:'), 'filter_status', $filters['status'],
    $status_options, ['spec_option' => _('All statuses')]);
// Deliberately text_row, not date_row: FA's date_cells() substitutes today's
// date into an empty date field, which would turn "no filter" into a filter
// that hides every run outside the current period.
text_row(_('Period From:'), 'filter_from_date', $filters['from_date'], 10, 10);
text_row(_('Period To:'), 'filter_to_date', $filters['to_date'], 10, 10);

submit_row('Filter', _('Filter'), false, '', false);
end_table(1);

// ----------------------------------------------------------------------------
// 2. Summary / list table
// ----------------------------------------------------------------------------

start_table(TABLESTYLE, 'width=95%');
table_header([
    _('Employee'),
    _('Period Start'),
    _('Period End'),
    _('Pay Date'),
    _('Gross Pay'),
    _('Deductions'),
    _('Net Pay'),
    _('Status'),
    _('GL'),
    '',
]);

foreach ($payrolls as $payroll) {
    $id = $payroll->getPayrollId();

    start_row();
    label_cell($payroll->getEmployeeName() !== null && $payroll->getEmployeeName() !== ''
        ? $payroll->getEmployeeName() : _('(unknown)'));
    label_cell(sql2date($payroll->getPayPeriodStart()));
    label_cell(sql2date($payroll->getPayPeriodEnd()));
    label_cell(sql2date($payroll->getPayDate()));
    amount_cell($payroll->getGrossPay());
    amount_cell($payroll->getTotalDeductions());
    amount_cell($payroll->getNetPay(), true);
    label_cell($payroll->getStatus());
    label_cell($payroll->isGlPosted() ? _('Posted') : _('No'));

    edit_button_cell('Edit' . $id, _('Edit'));
    submit_js_confirm('Delete' . $id,
        sprintf(_('Delete the payroll run for the period starting %s? Its earnings '
            . 'and deduction lines are deleted with it.'),
            sql2date($payroll->getPayPeriodStart())));
    delete_button_cell('Delete' . $id, _('Delete'));
    end_row();
}

if (count($payrolls) === 0) {
    start_row();
    label_cell(_('No payroll runs match the current filter.'), 'colspan=10');
    end_row();
}

end_table(1);

// ----------------------------------------------------------------------------
// 3. Data-entry form
// ----------------------------------------------------------------------------

start_table(TABLESTYLE2, 'width=95%');
table_section_title($editing ? _('Edit Payroll Run') : _('New Payroll Run'));

array_selector_row(_('Employee:'), 'person_id', $form_values['person_id'], $person_options);

// date_row renders from $_POST and defaults an empty field to today, so the
// entry-form dates have to be seeded there — otherwise an edit would show
// today rather than the stored period.
$_POST['pay_period_start'] = $form_values['pay_period_start'];
$_POST['pay_period_end'] = $form_values['pay_period_end'];
$_POST['pay_date'] = $form_values['pay_date'];

date_row(_('Period Start:'), 'pay_period_start');
date_row(_('Period End:'), 'pay_period_end');
amount_row(_('Gross Pay:'), 'gross_pay', $form_values['gross_pay']);
amount_row(_('Deductions:'), 'total_deductions', $form_values['total_deductions']);
label_row(_('Net Pay:'), price_format(
    (float) $form_values['gross_pay'] - (float) $form_values['total_deductions']));
date_row(_('Pay Date:'), 'pay_date');
array_selector_row(_('Status:'), 'status', $form_values['status'], $status_options);

submit_add_or_update_row(!$editing, false, '', false, false);

end_table(1);

if ($editing) {
    hidden('selected_id', $selected_id);
    submit_add_or_update_center(false, false, false);
} else {
    submit_add_or_update_center(true, false, false);
}

end_form();

display_note(_('Net pay is recalculated by the server as gross pay less deductions; '
    . 'the value typed here is only a preview and is not what gets stored. '
    . 'Individual earnings and deduction lines are held per payroll run and are '
    . 'not edited from this page.'), 0);

if (!$hrm_dispatched) {
    end_page();
}