<?php
/**
 * ksf_FA_HRM — legacy entry point. Forwarder only.
 *
 * The application moved to a single front controller, hrm.php. This file
 * exists so that:
 *   - FA's own extension index (and the sidebar menu in hooks.php, which links
 *     to modules/ksf_FA_HRM/index.php) keeps working, and
 *   - existing `index.php?view=employees` bookmarks keep resolving.
 *
 * It forwards internally rather than issuing a redirect, so a POST that was
 * already in flight when the module was upgraded keeps its body. $_SERVER is
 * rewritten to hrm.php as well, so the forms the tabs render post back to the
 * canonical URL and the browser's address bar converges on hrm.php.
 *
 * @package ksf_FA_HRM
 * @since   1.1.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §11 (host page = thin router)
 * @BABOK Related: FR-HRM-001 (app shell)
 */

// Rewrite the legacy `view=` selector onto the canonical `tab=`, keeping the
// rest of the query string (filters, ids, and the `page=` pager position)
// intact.
if (!isset($_GET['tab']) && isset($_GET['view']) && $_GET['view'] !== '') {
    $_GET['tab'] = (string) $_GET['view'];
}
unset($_GET['view']);

// A `page=` on the way in is a pager position, never a tab selector: hrm.php
// reads `tab=` for the tab and `page=` only for paging. An old `index.php?page=leave`
// link therefore lands on the default tab rather than the Leave tab — the
// `?page=<tab>` form was published for minutes and was never depended upon, so
// it was dropped rather than aliased.

// Make hrm.php the canonical self/uri so anything building a link or form
// action from $_SERVER points at the real controller.
if (isset($_SERVER['PHP_SELF'])) {
    $_SERVER['PHP_SELF'] = preg_replace('#/index\.php$#', '/hrm.php', $_SERVER['PHP_SELF']);
}
if (isset($_SERVER['SCRIPT_NAME'])) {
    $_SERVER['SCRIPT_NAME'] = preg_replace('#/index\.php$#', '/hrm.php', $_SERVER['SCRIPT_NAME']);
}
if (isset($_SERVER['REQUEST_URI'])) {
    $_SERVER['REQUEST_URI'] = preg_replace(
        '#/index\.php(\?|$)#',
        '/hrm.php$1',
        $_SERVER['REQUEST_URI']
    );
}

require __DIR__ . '/hrm.php';