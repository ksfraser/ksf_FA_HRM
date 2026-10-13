<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\App;

use ksfraser\FrontAccounting\Common\App\AbstractFrontController;
use ksfraser\FrontAccounting\Common\App\TabRegistration;

/**
 * HrmFrontController — HRM's front controller, reduced to a declaration.
 *
 * Everything the old hrm.php preamble did by hand — resolve the tab from `tab`
 * (with `view` as the deprecated alias), resolve the per-tab security area,
 * boot the shell so `hrm_register_tabs` can contribute tabs, normalise `$_GET`,
 * render the page and the menu, enforce access once for both the read and the
 * action path, dispatch, close — now lives in
 * {@see AbstractFrontController}. This subclass supplies only what is genuinely
 * HRM-specific:
 *
 *   - the page title ("HRM");
 *   - the <head> JavaScript, which today is the date-picker bundle needed by the
 *     date_row() controls on the payroll and leave entry forms.
 *
 * `run()` and `enforceSecurity()` are final in the base, so neither the dispatch
 * sequence nor the access check can be weakened here. If HRM ever needs to
 * pre-handle an action verb before its tab controller sees it, `dispatchAction()`
 * is the seam — and overriding it cannot widen access, because enforcement has
 * already run by then.
 *
 * PHP 7.3 compatible.
 *
 * @package ksf_FA_HRM
 * @since   1.1.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §11 (host page = thin router)
 * @BABOK Related: FR-HRM-001 (app shell front controller)
 */
class HrmFrontController extends AbstractFrontController
{
    /**
     * FA page title.
     *
     * @return string
     *
     * @since 1.1.0
     */
    protected function getPageTitle(): string
    {
        return _("HRM");
    }

    /**
     * Extra <head> JavaScript the resolved tab needs.
     *
     * Only the date-picker bundle today: the payroll and leave entry forms
     * render date_row() controls, which are inert without it. Every other tab
     * gets nothing.
     *
     * @param TabRegistration $tab Resolved tab
     *
     * @return string
     *
     * @since 1.1.0
     */
    protected function getPageJs(TabRegistration $tab): string
    {
        if (!in_array($tab->getKey(), ['payroll', 'leave'], true)) {
            return '';
        }
        if (!function_exists('user_use_date_picker') || !user_use_date_picker()) {
            return '';
        }

        return function_exists('get_js_date_picker') ? get_js_date_picker() : '';
    }
}
