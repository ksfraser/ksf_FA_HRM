<?php
/**
 * ksf_FA_HRM — pages/payroll.php. Deprecated forwarder shim.
 *
 * This tab moved into the single front controller ../hrm.php on 2026-10-12.
 * The page body now lives in src/Controller/PayrollTabController.php
 * and is reached as:
 *
 *     ../hrm.php?page=payroll
 *
 * This shim exists for one release so existing bookmarks and any hard-coded
 * links keep resolving. It forwards internally rather than redirecting, so an
 * in-flight POST keeps its body. Remove it in the next major version.
 *
 * Security: SA_HRM_PAYROLL — now enforced by hrm.php from the tab's registered
 * security area (see ../hooks.php::install_access).
 *
 * @package ksf_FA_HRM
 * @since   1.1.0 (deprecated — use ../hrm.php?page=payroll)
 *
 * @deprecated 1.1.0 Forwarder to ../hrm.php?page=payroll
 */

// The tab's own security area is resolved and enforced by hrm.php, which reads
// it from the tab registration before session.inc runs.

$_GET['page'] = 'payroll';

// Point self/uri at the canonical controller so the rendered links and form
// actions converge on hrm.php instead of bouncing through this shim.
if (isset($_SERVER['PHP_SELF'])) {
    $_SERVER['PHP_SELF'] = preg_replace('#/pages/payroll\.php$#', '/hrm.php', $_SERVER['PHP_SELF']);
}
if (isset($_SERVER['SCRIPT_NAME'])) {
    $_SERVER['SCRIPT_NAME'] = preg_replace('#/pages/payroll\.php$#', '/hrm.php', $_SERVER['SCRIPT_NAME']);
}
if (isset($_SERVER['REQUEST_URI'])) {
    $_SERVER['REQUEST_URI'] = preg_replace(
        '#/pages/payroll\.php(\?|$)#',
        '/hrm.php$1',
        $_SERVER['REQUEST_URI']
    );
}

// hrm.php bootstraps FA with $path_to_root = '../..', which PHP resolves
// against the MAIN script's directory. This shim is the main script and lives one
// level deeper (pages/), so move to the module root first or the include of
// ../../includes/session.inc lands above the FA root and fatals.
chdir(__DIR__ . '/..');

require __DIR__ . '/../hrm.php';
