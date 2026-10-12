<?php
/**
 * ksf_FA_HRM Entry Point
 *
 * App-shell router: resolves the ?view= tab from the HrmAppShell, sets the
 * per-view security BEFORE session.inc, then boots the shell (fires the
 * `hrm_register_tabs` register-with-me hook so other modules can add tabs),
 * renders the sub-menu and dispatches to the tab controller SRP or page script.
 *
 * @package ksf_FA_HRM
 * @since 1.0.0
 */

require_once __DIR__ . '/ComposerDependencies.php';
\ksfraser\FrontAccounting\HRM\Utils\ComposerDependencies::ensure(__DIR__);

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

$path_to_root = "../..";

$appShell = new \ksfraser\FrontAccounting\HRM\App\HrmAppShell();

$view = isset($_GET['view']) ? (string) $_GET['view'] : $appShell->getDefaultView();
if ($appShell->getTab($view) === null) {
    $view = $appShell->getDefaultView();
}

$page_security = $appShell->getSecurity($view, 'SA_ksf_FA_HRMVIEW');
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

// FA's bootstrap loads only ui_view/ui_controls/ui_msgs; ui_input.inc and
// ui_lists.inc must be pulled in by the page. Without this every helper this
// module's tabs use -- start_table is fine, but text_row(), amount_row(),
// date_row(), array_selector_row(), submit_row(), submit_center() and the
// edit/delete button cells all live in ui_input.inc / ui_lists.inc -- is an
// undefined function once the tab is actually rendered. Same fix CRM applies
// in its own index.php.
include_once($path_to_root . "/includes/ui.inc");

// Fire the register-with-me hook: other modules may add their tabs now.
$appShell->boot();

page(_("HRM"), false, false, '', '');

echo $appShell->renderMenu($view);

// Tabs that are backed by a legacy page script rather than an
// AbstractTabController SRP have that script *included* by dispatch(). Those
// scripts are also reachable directly by URL, so they need to know whether FA
// has already been booted and the page chrome opened by this file. Setting
// HRM_APP_SHELL_DISPATCH lets a page skip its own session.inc/page()/end_page()
// round trip and render only its body, instead of nesting a second document
// inside this one.
define('HRM_APP_SHELL_DISPATCH', true);

$appShell->dispatch($view);

end_page();