<?php
/**
 * HRM — Recruitment.
 *
 * Layout follows the CRM pattern: filter controls at the top, the list table
 * beneath them, then the data-entry form.
 *
 *   1. Filter  — department and name fragment.
 *   2. List    — candidates.
 *   3. Form    — add a candidate.
 *
 * STATUS: there is no recruitment data model in this module yet. ksf_FA_HRM's
 * schema owns no vacancy / applicant / application tables, and the tab is
 * registered against SA_HRM_RECRUITMENT but was never backed by one. Rather
 * than render a page whose SQL would be invented against tables that do not
 * exist, the page renders the full FA structure and reports the candidate list
 * as empty. Once 0_hrm_vacancies / 0_hrm_candidates exist, only the three
 * calls into HrmRecruitmentService below need real bodies — the page itself
 * does not change.
 *
 * Security area: SA_HRM_RECRUITMENT — defined by
 * hooks_ksf_FA_HRM::install_access() in ../hooks.php
 * (SS_ksf_FA_HRM | 8, "Recruitment").
 *
 * Dual use: reachable directly by URL, and also *included* by ../index.php
 * when HrmAppShell dispatches the recruitment tab.
 *
 * PHP 7.3 compatible.
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §10 (page -> service -> repository)
 * @BABOK Related: FR-HRM-001 (recruitment — placeholder)
 */

$hrm_dispatched = defined('HRM_APP_SHELL_DISPATCH');

if (!$hrm_dispatched) {
    $page_security = 'SA_HRM_RECRUITMENT';
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

use ksfraser\FrontAccounting\HRM\Service\DepartmentService;

$departments = new DepartmentService();

if (!$hrm_dispatched) {
    page(_($help_context = 'Recruitment'), false, false, '', '');
}

simple_page_mode(true);

$filters = [
    'department_id' => (int) get_post('filter_department_id', 0),
    'name_fragment' => (string) get_post('filter_name_fragment', ''),
];

$department_options = $departments->optionsFor();

// No candidate store exists yet; see the file header.
$candidates = [];
$filters_active = ($filters['department_id'] > 0 || trim($filters['name_fragment']) !== '');

start_form();

// ----------------------------------------------------------------------------
// 1. Filter bar
// ----------------------------------------------------------------------------

start_table(TABLESTYLE2, 'width=95%');
table_section_title(_('Filter'));

array_selector_row(_('Department:'), 'filter_department_id', $filters['department_id'],
    $department_options, ['spec_option' => _('All departments')]);
text_row(_('Name Contains:'), 'filter_name_fragment', $filters['name_fragment'], 30, 60);

submit_row('Filter', _('Filter'), false, '', false);
end_table(1);

// ----------------------------------------------------------------------------
// 2. List table
// ----------------------------------------------------------------------------

start_table(TABLESTYLE, 'width=95%');
table_header([
    _('Candidate'),
    _('Position'),
    _('Department'),
    _('Applied'),
    _('Status'),
    '',
]);

if (count($candidates) > 0) {
    foreach ($candidates as $candidate) {
        $id = (int) $candidate['candidate_id'];
        start_row();
        label_cell($candidate['candidate_name']);
        label_cell($candidate['position_title']);
        label_cell($candidate['department_name']);
        label_cell($candidate['applied_on'] !== '' ? sql2date($candidate['applied_on']) : '');
        label_cell($candidate['status']);
        delete_button_cell('Delete' . $id, _('Delete'));
        submit_js_confirm('Delete' . $id,
            sprintf(_('Delete the application for %s?'), $candidate['candidate_name']));
        end_row();
    }
} else {
    start_row();
    label_cell($filters_active
        ? _('No candidates match the current filter.')
        : _('No candidates have been recorded yet.'), 'colspan=6');
    end_row();
}

end_table(1);

// ----------------------------------------------------------------------------
// 3. Data-entry form
// ----------------------------------------------------------------------------

start_table(TABLESTYLE2, 'width=95%');
table_section_title(_('Add Candidate'));

text_row(_('Candidate Name:'), 'candidate_name', '', 40, 100);
text_row(_('Position:'), 'position_title', '', 40, 100);
array_selector_row(_('Department:'), 'department_id', '', $department_options);
date_row(_('Applied On:'), 'applied_on');
array_selector_row(_('Status:'), 'candidate_status', 'New', [
    'New' => _('New'),
    'Screening' => _('Screening'),
    'Interview' => _('Interview'),
    'Offered' => _('Offered'),
    'Hired' => _('Hired'),
    'Rejected' => _('Rejected'),
]);

submit_row('Allocate', _('Add Candidate'), false, '', false);

end_table(1);

submit_center(_('Add Candidate'), 'Allocate');

end_form();

display_note(_('Recruitment is not implemented in this module: ksf_FA_HRM ships no '
    . 'vacancy, candidate or application tables, so this page cannot store the '
    . 'entry above. The structure is in place and the access area '
    . '(SA_HRM_RECRUITMENT) is real; the persistence layer is the missing piece.'), 0);

if (!$hrm_dispatched) {
    end_page();
}