<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\App;

use ksfraser\FrontAccounting\Common\App\AbstractAppShell;
use ksfraser\FrontAccounting\HRM\Controller\BenefitsTabController;
use ksfraser\FrontAccounting\HRM\Controller\DepartmentsTabController;
use ksfraser\FrontAccounting\HRM\Controller\EmployeesTabController;
use ksfraser\FrontAccounting\HRM\Controller\GradesTabController;
use ksfraser\FrontAccounting\HRM\Controller\LeaveTabController;
use ksfraser\FrontAccounting\HRM\Controller\LeaveTypesTabController;
use ksfraser\FrontAccounting\HRM\Controller\PayrollTabController;
use ksfraser\FrontAccounting\HRM\Controller\PositionsTabController;
use ksfraser\FrontAccounting\HRM\Controller\RecruitmentTabController;
use ksfraser\FrontAccounting\HRM\Controller\ReportsTabController;
use ksfraser\FrontAccounting\HRM\Controller\RolesTabController;
use ksfraser\FrontAccounting\HRM\Controller\TeamsTabController;

/**
 * HrmAppShell — HRM application shell SRP.
 *
 * Registers the HRM core tabs (employees, departments, positions, ...), then
 * fires the `hrm_register_tabs` register-with-me hook on boot() so any other
 * module can register its own tabs into the HRM app.
 *
 * The app id is `hrm`, which is what derives the hook name: another module adds
 * a tab by exposing `hrm_register_tabs(&$data)` on its hooks class. See
 * README.md ("Adding an HRM tab from another module").
 *
 * Core tabs are registered at construction time so the host page can resolve
 * the per-view security area BEFORE session.inc. External tabs contributed via
 * the hook carry their own security and are merged into the menu + dispatch.
 *
 * Every tab is controller-backed: there is no `pageFile` fallback left, and
 * hrm.php is the single front controller for the whole application. The former
 * pages/*.php scripts (payroll, leave, recruitment, reports) are now thin
 * forwarder shims onto hrm.php.
 *
 * PHP 7.3 compatible.
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §11 (app host)
 * @BABOK Related: FR-HRM-001 (App shell tab registration)
 */
class HrmAppShell extends AbstractAppShell
{
    /**
     * @param string $defaultView Fallback view key
     *
     * @since 1.0.0
     */
    public function __construct(string $defaultView = 'employees')
    {
        // viewParam 'tab' is the user-facing tab selector: `hrm.php?tab=leave`.
        //
        // It is deliberately NOT 'page'. AbstractTabController::page() reads
        // $_GET['page'] for the pager position and MasterSummaryTable::pagerUrl()
        // writes `?page=<n>` into its pager links, so using 'page' here would
        // have two meanings in one parameter and force a heuristic to tell them
        // apart. `page` means pagination and nothing else; `tab` means the tab.
        //
        // The controllers' preserveParams() returns ['tab'] so pager links carry
        // the tab alongside the page number, e.g. `?page=2&tab=leave`.
        parent::__construct('hrm', 'hrm.php', 'tab', $defaultView);
        $this->registerCoreTabs();
    }

    /**
     * Instantiate the controller for a tab, with the tab key injected so the
     * controller can build links that keep the tab selected.
     *
     * Overrides AbstractAppShell::createController() — the base news the class
     * up with no arguments, which leaves the controller unable to tell which
     * tab it is serving.
     *
     * @param \ksfraser\FrontAccounting\Common\App\TabRegistration $tab
     *
     * @return object|null
     *
     * @since 1.1.0
     */
    protected function createController(\ksfraser\FrontAccounting\Common\App\TabRegistration $tab)
    {
        $class = $tab->getControllerClass();
        if ($class === null || !class_exists($class)) {
            return null;
        }
        return new $class(null, ['view' => $tab->getKey()]);
    }

    /**
     * Core HRM tabs, each mapped to its controller class and security area.
     *
     * Every tab is controller-backed, so dispatch() always runs a controller's
     * run(). The filter-menu / summary-table / data-entry-form tabs (payroll,
     * leave, recruitment, reports) extend FilteredTabController, which adds
     * the filter bar the plain AbstractTabController has no slot for.
     *
     * Roles and Teams were previously unreachable: pages/roles.php and
     * pages/teams.php existed but neither tab was registered here, so
     * dispatch() had nothing to resolve and the pages could never render.
     *
     * @return void
     *
     * @since 1.0.0
     */
    protected function registerCoreTabs(): void
    {
        $controllers = [
            'employees'   => EmployeesTabController::class,
            'departments' => DepartmentsTabController::class,
            'positions'   => PositionsTabController::class,
            'grades'      => GradesTabController::class,
            'roles'       => RolesTabController::class,
            'teams'       => TeamsTabController::class,
            'payroll'     => PayrollTabController::class,
            'benefits'    => BenefitsTabController::class,
            'leave'       => LeaveTabController::class,
            'leave_types' => LeaveTypesTabController::class,
            'recruitment' => RecruitmentTabController::class,
            'reports'     => ReportsTabController::class,
        ];

        $tabs = [
            ['key' => 'employees',   'label' => 'Employees',   'security' => 'SA_HRM_EMPLOYEE'],
            ['key' => 'departments', 'label' => 'Departments', 'security' => 'SA_HRM_DEPARTMENT'],
            ['key' => 'positions',   'label' => 'Positions',   'security' => 'SA_ksf_FA_HRMMANAGE'],
            ['key' => 'grades',      'label' => 'Grades',      'security' => 'SA_ksf_FA_HRMMANAGE'],
            ['key' => 'roles',       'label' => 'Roles',       'security' => 'SA_HRM_ROLES'],
            ['key' => 'teams',       'label' => 'Teams',       'security' => 'SA_HRM_TEAMS'],
            ['key' => 'payroll',     'label' => 'Payroll',     'security' => 'SA_HRM_PAYROLL'],
            ['key' => 'benefits',    'label' => 'Benefits',    'security' => 'SA_HRM_BENEFITS'],
            ['key' => 'leave',       'label' => 'Leave',       'security' => 'SA_HRM_LEAVE'],
            ['key' => 'leave_types', 'label' => 'Leave Types', 'security' => 'SA_HRM_LEAVE'],
            ['key' => 'recruitment', 'label' => 'Recruitment', 'security' => 'SA_HRM_RECRUITMENT'],
            ['key' => 'reports',     'label' => 'Reports',     'security' => 'SA_ksf_FA_HRMVIEW'],
        ];

        $priority = 0;
        foreach ($tabs as $tab) {
            $key = (string) $tab['key'];
            $this->registerTabFromArray([
                'key'              => $key,
                'label'            => $tab['label'],
                'security'         => $tab['security'],
                'controller_class' => isset($controllers[$key]) ? $controllers[$key] : null,
                'priority'         => $priority,
                'order'            => 0,
                // No page_file: hrm.php is the only entry point, and every tab
                // is served by a controller.
                'page_file'        => null,
                'options'          => ['view' => $key],
            ]);
            $priority += 10;
        }
    }
}