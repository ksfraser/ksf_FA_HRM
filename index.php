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

// Fire the register-with-me hook: other modules may add their tabs now.
$appShell->boot();

page(_("HRM"), false, false, '', '');

echo $appShell->renderMenu($view);

$appShell->dispatch($view);

end_page();