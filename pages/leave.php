<?php
/**
 * HRM — Leave Management.
 *
 * Layout follows the CRM pattern: filter controls at the top, the summary /
 * list table beneath them, then the data-entry form.
 *
 *   1. Filter  — employee, leave type, leave year, plus Filter / Clear.
 *   2. List    — leave balances (allocated / carried over / used / remaining).
 *   3. Form    — allocate, or adjust an existing allocation.
 *
 * The page is thin: every query and every write goes through
 * LeaveBalanceService and LeaveBalanceRepository. No SQL lives here.
 *
 * Security area: SA_HRM_LEAVE — defined by hooks_ksf_FA_HRM::install_access()
 * in ../hooks.php (SS_ksf_FA_HRM | 7, "Leave Management").
 *
 * Dual use: this script is reachable directly by URL, and is also *included*
 * by ../index.php when HrmAppShell dispatches the leave tab. When included,
 * FA is already booted and page() has already been called, so the bootstrap is
 * skipped and only the page body is rendered.
 *
 * PHP 7.3 compatible.
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §10 (page -> service -> repository)
 * @BABOK Related: FR-HRM-001-005 (leave balances)
 */

$hrm_dispatched = defined('HRM_APP_SHELL_DISPATCH');

if (!$hrm_dispatched) {
    $page_security = 'SA_HRM_LEAVE';
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

use ksfraser\FrontAccounting\HRM\Service\LeaveBalanceService;

$service = new LeaveBalanceService();

$js = '';
if (function_exists('user_use_date_picker') && user_use_date_picker()) {
    $js .= get_js_date_picker();
}

if (!$hrm_dispatched) {
    page(_($help_context = 'Leave Management'), false, false, '', $js);
}

// ----------------------------------------------------------------------------
// simple_page_mode() resolves the submit button into $Mode / $selected_id.
// 'Edit<id>' / 'Delete<id>' list buttons carry the row id in the button name,
// which is how core FA list pages address rows (see admin/fiscalyears.php).
// ----------------------------------------------------------------------------

simple_page_mode(true);

$filters = [
    'person_id' => (int) get_post('filter_person_id', 0),
    'leave_type_id' => (int) get_post('filter_leave_type_id', 0),
    'year' => (int) get_post('filter_year', 0),
];

$person_options = $service->personOptions();
$type_options = $service->leaveTypeOptions();

$form_values = [
    'person_id' => '',
    'leave_type_id' => '',
    'year' => (string) (int) date('Y'),
    'allocated_days' => '',
    'carried_over' => '',
];
$editing = false;

// ----------------------------------------------------------------------------
// POST handling — no SQL here, the service owns every write
// ----------------------------------------------------------------------------

if ($Mode === 'ADD_ITEM' || $Mode === 'UPDATE_ITEM') {
    $posted = [
        'person_id' => get_post('person_id', 0),
        'leave_type_id' => get_post('leave_type_id', 0),
        'year' => get_post('year', 0),
        'allocated_days' => get_post('allocated_days', 0),
        'carried_over' => get_post('carried_over', 0),
    ];

    $result = $service->allocate($posted);

    if ($result['ok']) {
        display_notification(_('Leave balance has been saved'));
        $Mode = 'RESET';
    } else {
        display_error($result['error']);
        $form_values = [
            'person_id' => (string) $posted['person_id'],
            'leave_type_id' => (string) $posted['leave_type_id'],
            'year' => (string) $posted['year'],
            'allocated_days' => (string) $posted['allocated_days'],
            'carried_over' => (string) $posted['carried_over'],
        ];
    }
}

if ($Mode === 'Delete') {
    $result = $service->release($selected_id);
    if ($result['ok']) {
        display_notification(_('Leave balance has been released'));
    } else {
        display_error($result['error']);
    }
    $Mode = 'RESET';
}

if ($Mode === 'Edit') {
    $row = $service->find($selected_id);
    if ($row !== null) {
        $form_values = [
            'person_id' => (string) $row['person_id'],
            'leave_type_id' => (string) $row['leave_type_id'],
            'year' => (string) $row['year'],
            'allocated_days' => (string) $row['allocated_days'],
            'carried_over' => (string) $row['carried_over'],
        ];
        $editing = true;
    } else {
        display_error(_('The selected leave balance could not be found.'));
    }
}

$balances = $service->search($filters);

start_form();

// ----------------------------------------------------------------------------
// 1. Filter bar
// ----------------------------------------------------------------------------

start_table(TABLESTYLE2, 'width=95%');
table_section_title(_('Filter'));

array_selector_row(_('Employee:'), 'filter_person_id', $filters['person_id'],
    $person_options, ['spec_option' => _('All employees')]);
array_selector_row(_('Leave Type:'), 'filter_leave_type_id', $filters['leave_type_id'],
    $type_options, ['spec_option' => _('All leave types')]);
text_row(_('Leave Year:'), 'filter_year',
    $filters['year'] > 0 ? (string) $filters['year'] : '', 4, 4);

submit_row('Filter', _('Filter'), false, '', false);
end_table(1);

// ----------------------------------------------------------------------------
// 2. Summary / list table
// ----------------------------------------------------------------------------

start_table(TABLESTYLE, 'width=95%');
table_header([
    _('Employee'),
    _('Leave Type'),
    _('Year'),
    _('Allocated'),
    _('Carried Over'),
    _('Used'),
    _('Balance'),
    '',
    '',
]);

foreach ($balances as $balance) {
    $id = (int) $balance['balance_id'];

    start_row();
    label_cell($balance['employee_name'] !== null && $balance['employee_name'] !== ''
        ? $balance['employee_name'] : _('(unknown)'));
    label_cell($balance['leave_type_name']);
    label_cell((string) $balance['year']);
    qty_cell($balance['allocated_days'], false, 2);
    qty_cell($balance['carried_over'], false, 2);
    qty_cell($balance['used_days'], false, 2);
    qty_cell($balance['balance'], true, 2);

    edit_button_cell('Edit' . $id, _('Edit'));
    delete_button_cell('Delete' . $id, _('Delete'));
    submit_js_confirm('Delete' . $id,
        sprintf(_('Release the leave balance for %s? Only balances with nothing used can be released.'),
            $balance['employee_name'] !== null && $balance['employee_name'] !== ''
                ? $balance['employee_name'] : _('this employee')));
    end_row();
}

if (count($balances) === 0) {
    start_row();
    label_cell(_('No leave balances match the current filter.'), 'colspan=9');
    end_row();
}

end_table(1);

// ----------------------------------------------------------------------------
// 3. Data-entry form
// ----------------------------------------------------------------------------

start_table(TABLESTYLE2, 'width=95%');
table_section_title($editing ? _('Adjust Leave Balance') : _('Allocate Leave'));

array_selector_row(_('Employee:'), 'person_id', $form_values['person_id'], $person_options);
array_selector_row(_('Leave Type:'), 'leave_type_id', $form_values['leave_type_id'], $type_options);
text_row(_('Leave Year:'), 'year', $form_values['year'], 4, 4);
qty_row(_('Allocated Days:'), 'allocated_days', $form_values['allocated_days'], '', 2);
qty_row(_('Carried Over Days:'), 'carried_over', $form_values['carried_over'], '', 2);

submit_add_or_update_row(!$editing, false, '', false, false);

end_table(1);

if ($editing) {
    hidden('selected_id', $selected_id);
    submit_add_or_update_center(false, false, false);
} else {
    submit_add_or_update_center(true, false, false);
}

end_form();

display_note(_('Days entered against an existing employee / type / year are added to '
    . 'the stored allocation. Consuming a balance happens through the leave approval '
    . 'workflow, which increments used days when a request is approved.'), 0);

if (!$hrm_dispatched) {
    end_page();
}