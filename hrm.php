<?php
/**
 * ksf_FA_HRM — single front controller for the whole application.
 *
 * Replaces the four standalone pages/*.php scripts (payroll, leave,
 * recruitment, reports) and absorbs the routing that index.php used to do. All
 * twelve HRM tabs are resolved here through HrmAppShell and served by a tab
 * controller; pages/*.php remain only as forwarder shims onto this file.
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
 *   link the app emits carries.
 *
 *   `page` is PAGINATION and nothing else. AbstractTabController::page() reads
 *   it for the pager position and MasterSummaryTable::pagerUrl() writes it into
 *   its pager links, so it had a second, conflicting meaning as a tab selector.
 *   Resolving the tab from `tab` instead removes the ambiguity entirely: there
 *   is no heuristic left to get wrong, and a hypothetical future tab with a
 *   numeric key can no longer be mistaken for a pager position.
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

// ---------------------------------------------------------------------------
// Helpers — declared before use. Conditional function bodies are not hoisted by
// PHP, so these must appear above the bootstrap flow that calls them.
// ---------------------------------------------------------------------------

/**
 * Resolve the requested tab key from the request.
 *
 * `tab` is the selector. `view` is a deprecated alias kept for index.php's
 * long-standing `?view=` bookmarks; it is consulted only when `tab` is absent.
 * `page` is deliberately NOT consulted — it is the pager position, and treating
 * it as a tab selector is the collision this design removes. Anything
 * unrecognised falls back to the shell's default view rather than erroring, so a
 * stale bookmark lands on a working tab rather than a blank page.
 *
 * @param object $appShell HrmAppShell instance
 *
 * @return string Resolved tab key
 *
 * @since 1.1.0
 */
function hrm_resolve_view($appShell)
{
    $candidates = [];

    if (isset($_GET['tab']) && $_GET['tab'] !== '') {
        $candidates[] = (string) $_GET['tab'];
    }
    if (isset($_GET['view']) && $_GET['view'] !== '') {
        $candidates[] = (string) $_GET['view'];
    }

    foreach ($candidates as $candidate) {
        if ($appShell->getTab($candidate) !== null) {
            return $candidate;
        }
    }

    return $appShell->getDefaultView();
}

/**
 * Route an `action=` request to the resolved tab's controller.
 *
 * The controller decides whether it handles the verb; an unrecognised action
 * falls through to a normal render so a mistyped URL still shows the tab
 * rather than a blank body.
 *
 * @param object $appShell HrmAppShell instance
 * @param string $view     Resolved tab key
 * @param string $action   Action verb
 *
 * @return void
 *
 * @since 1.1.0
 */
function hrm_dispatch_action($appShell, $view, $action)
{
    $unavailable = function_exists('_')
        ? _('This action is not available for the selected tab.')
        : 'This action is not available for the selected tab.';

    $tab = $appShell->getTab($view);
    if ($tab === null || !$tab->hasController()) {
        echo '<p>' . $unavailable . '</p>';
        return;
    }

    // The tab's own security area, re-checked here because dispatch() is what
    // normally enforces it and an action request bypasses dispatch().
    $security = $tab->getSecurity();
    if ($security !== '' && function_exists('check_page_security')) {
        check_page_security($security);
    }

    $class = $tab->getControllerClass();
    if ($class === null || !class_exists($class)) {
        echo '<p>' . $unavailable . '</p>';
        return;
    }

    $controller = new $class(null, ['view' => $view]);
    if (!method_exists($controller, 'runAction')) {
        echo '<p>' . $unavailable . '</p>';
        return;
    }

    if ($controller->runAction($action) === true) {
        return;
    }

    // Verb not handled — render the tab normally.
    $appShell->dispatch($view);
}

/**
 * Extra <head> JavaScript a tab needs.
 *
 * Only the date-picker bundle today: the entry forms of the payroll and leave
 * tabs render date_row() controls, which are inert without it.
 *
 * @param string $view Resolved tab key
 *
 * @return string
 *
 * @since 1.1.0
 */
function hrm_page_js($view)
{
    if (!in_array($view, ['payroll', 'leave'], true)) {
        return '';
    }
    if (!function_exists('user_use_date_picker') || !user_use_date_picker()) {
        return '';
    }
    return function_exists('get_js_date_picker') ? get_js_date_picker() : '';
}

// ---------------------------------------------------------------------------
// Bootstrap / dispatch
// ---------------------------------------------------------------------------

require_once __DIR__ . '/ComposerDependencies.php';
\ksfraser\FrontAccounting\HRM\Utils\ComposerDependencies::ensure(__DIR__);

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

$path_to_root = '../..';

$appShell = new \ksfraser\FrontAccounting\HRM\App\HrmAppShell();

$view = hrm_resolve_view($appShell);

// The security area must be known BEFORE session.inc, and it can only come from
// the tabs registered at construction time — the hrm_register_tabs hook fires
// on boot(), which needs a session. Tabs contributed by other modules therefore
// carry a security area the app falls back to.
$page_security = $appShell->getSecurity($view, 'SA_ksf_FA_HRMVIEW');
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

// FA's bootstrap loads only ui_view/ui_controls/ui_msgs; ui_input.inc and
// ui_lists.inc must be pulled in by the page. Without this every helper the
// tabs use — text_row(), amount_row(), date_row(), array_selector_row(),
// submit_row(), the edit/delete button cells — is an undefined function once a
// tab is actually rendered.
include_once($path_to_root . "/includes/ui.inc");

// Fire the register-with-me hook: other modules may add their tabs now.
$appShell->boot();

// The pre-session resolution above only knows the tabs registered in
// HrmAppShell's constructor. A tab contributed by another module through
// hrm_register_tabs did not exist yet, so re-resolve now and pick it up.
// dispatch() re-checks the tab's own security area after boot(), so accepting
// it here cannot widen access — a tab whose area the user lacks is refused at
// that point.
if ($view === $appShell->getDefaultView()) {
    $late = hrm_resolve_view($appShell);
    if ($late !== $view) {
        $view = $late;
    }
}

// Normalise the resolved tab back into $_GET['tab'] so the links the app emits
// carry it verbatim:
//
//   - MasterSummaryTable::pagerUrl() copies each name in the table's
//     `preserve_params` list straight out of $_GET, and the controllers pass
//     ['tab'] — so pager links read `?page=3&tab=leave`;
//   - TabActionRouting::hrmActionUrl() starts from $_GET when rebuilding the
//     post-action redirect URL.
//
// Writing `tab` rather than the old `view` also means an arrival on the
// deprecated `?view=` alias is normalised onto the canonical parameter.
// $_GET['page'] is left untouched: it is the pager position and must survive
// into pager links and form actions verbatim.
$_GET['tab'] = $view;
unset($_GET['view']);

$action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';

page(_("HRM"), false, false, '', hrm_page_js($view));

echo $appShell->renderMenu($view);

if ($action !== '') {
    hrm_dispatch_action($appShell, $view, $action);
} else {
    $appShell->dispatch($view);
}

end_page();