<?php
/**
 * ksf_FA_HRM — single front controller for the whole application.
 *
 * This file is the whole preamble. Everything it used to do by hand — resolving
 * the tab, resolving the per-tab security area, booting the shell so
 * `hrm_register_tabs` can contribute tabs, normalising `$_GET`, rendering the
 * page and the tab menu, enforcing access once, and dispatching either the
 * action verb or the tab — now lives in
 * `ksfraser\FrontAccounting\Common\App\AbstractFrontController` (package
 * `ksfraser/ksf-fa-common`). What is left here is the two-phase bootstrap FA
 * forces on a page: phase A needs the include at GLOBAL scope, phase B is the
 * controller's `run()`.
 *
 * URL shape
 * ---------
 *   hrm.php                                 default tab (employees)
 *   hrm.php?tab=payroll                     read the payroll tab
 *   hrm.php?tab=leave&filter_year=2026      read with a filter applied
 *   hrm.php?tab=leave&page=3                Leave tab, pager position 3
 *   hrm.php?tab=payroll&action=edit&id=7    pre-load the entry form for run 7
 *   hrm.php?tab=payroll&action=save         POST only
 *   hrm.php?tab=payroll&action=delete&id=7  POST only (CSRF)
 *
 * Two parameters, two jobs, no collision:
 *
 *   `tab` is the TAB SELECTOR — what the user types, what the menu renders
 *   (HrmAppShell passes 'tab' as AbstractAppShell's viewParam), and what every
 *   link the app emits carries. `page` is PAGINATION and nothing else, so a
 *   hypothetical tab keyed "3" can never be mistaken for a pager position.
 *
 *   Pager links keep the tab because the controllers pass `preserve_params =>
 *   ['tab']` to MasterSummaryTable, which copies those `$_GET` keys verbatim
 *   onto the links it builds: `?page=3&tab=leave`.
 *
 *   `?view=<tab>` is still accepted as a DEPRECATED ALIAS for `tab` because
 *   index.php used `?view=` long before hrm.php existed. `?page=<tab>` is NOT:
 *   it was published for minutes, was never depended upon, and keeping it would
 *   re-introduce the exact collision this rename removes.
 *
 * The app id is `hrm`, so boot() fires `hook_invoke_all('hrm_register_tabs')`
 * and another module can add its own tab to the HRM menu. See README.md.
 *
 * `session.inc` is deliberately NOT inside run(): it reads `$page_security` and
 * `$path_to_root` as GLOBALS in the including scope, so from inside a method it
 * would see neither. Hence phase A here, phase B below.
 *
 * This file is TWO levels below the FA root, which is why $path_to_root is
 * '../..' and not '../..' of pages/ (three levels). Getting that wrong is
 * exactly what stopped the old pages/*.php scripts bootstrapping correctly.
 *
 * PHP 7.3 compatible.
 *
 * @package ksf_FA_HRM
 * @since   1.1.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §11 (host page = thin router)
 * @BABOK Related: FR-HRM-001 (app shell)
 */

require_once __DIR__ . '/ComposerDependencies.php';
\ksfraser\FrontAccounting\HRM\Utils\ComposerDependencies::ensure(__DIR__);

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

$path_to_root = '../..';

// The fallback security area is a REQUIRED constructor argument and is never
// empty: it is what makes access enforcement fail closed for a tab contributed
// by another module that registers without an area of its own.
$frontController = new \ksfraser\FrontAccounting\HRM\App\HrmFrontController(
    new \ksfraser\FrontAccounting\HRM\App\HrmAppShell(),
    'SA_ksf_FA_HRMVIEW',
    $path_to_root,
    ['load_ui_includes' => false]
);

// Phase A (global scope): the area must be known BEFORE session.inc, and it can
// only come from the tabs registered at shell-construction time — the
// hrm_register_tabs hook fires on boot(), which needs a session. A tab
// contributed by another module therefore opens the session under the fallback
// area, and run() re-checks it against its OWN area once boot() has run.
$page_security = $frontController->getSecurityArea();
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

// FA's bootstrap loads only ui_view/ui_controls/ui_msgs. ui_input.inc and
// ui_lists.inc must be pulled in by the page, or every helper the tab
// controllers use — text_row(), amount_row(), date_row(),
// array_selector_row(), submit_row(), the edit/delete button cells — is an
// undefined function.
//
// This include has to happen HERE, at global scope, and the base class's own
// load_ui_includes step is therefore switched off. includes/ui.inc reads
// `$path_to_root` as a global in its including scope, so AbstractFrontController
// cannot do it from inside loadUiIncludes(): the variable is a method local
// there, every include inside ui.inc resolves to the filesystem root, and all
// of them fail silently and every UI helper stays undefined. Same reason
// session.inc cannot move into run().
include_once($path_to_root . "/includes/ui.inc");

// Phase B: boot() + re-resolve, $_GET normalisation, page and menu render,
// the single access check, then dispatch.
$frontController->run();
