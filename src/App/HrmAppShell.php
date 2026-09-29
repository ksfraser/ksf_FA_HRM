<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\App;

use ksfraser\FrontAccounting\Common\App\AbstractAppShell;
use ksfraser\FrontAccounting\Common\App\TabRegistration;
use ksfraser\FrontAccounting\HRM\Controller\BenefitsTabController;
use ksfraser\FrontAccounting\HRM\Controller\DepartmentsTabController;
use ksfraser\FrontAccounting\HRM\Controller\EmployeesTabController;
use ksfraser\FrontAccounting\HRM\Controller\GradesTabController;
use ksfraser\FrontAccounting\HRM\Controller\LeaveTypesTabController;
use ksfraser\FrontAccounting\HRM\Controller\PositionsTabController;
use ksfraser\FrontAccounting\HRM\Controller\RolesTabController;
use ksfraser\FrontAccounting\HRM\Controller\TeamsTabController;

/**
 * HrmAppShell — HRM application shell SRP.
 *
 * Registers the HRM core tabs (employees, departments, positions, ...),
 * then fires the `hrm_register_tabs` register-with-me hook on boot() so any
 * other module can register its own tabs into the HRM app.
 *
 * Core tabs are registered at construction time so the host page can resolve
 * the per-view security area BEFORE session.inc. External tabs contributed via
 * the hook carry their own security and are merged into the menu + dispatch.
 *
 * PHP 7.3 compatible.
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §7 (app host)
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
        parent::__construct('hrm', 'index.php', 'view', $defaultView);
        $this->registerCoreTabs();
    }

    /**
     * Core HRM tabs, each mapped to its page script + security area.
     *
     * The eight add-link catalogue tabs (employees, departments, positions,
     * grades, benefits, leave_types, roles, teams) are controller-backed and
     * are NOT given a page script — the controller owns the whole tab, so
     * dispatch() never falls through to pages/<key>.php. The remaining tabs
     * (payroll, leave, recruitment, reports) keep their page scripts.
     *
     * Roles and Teams were previously unreachable: pages/roles.php and
     * pages/teams.php existed but neither tab was registered here, so
     * dispatch() had nothing to resolve and the pages could never render.
     * They are now registered and controller-backed.
     *
     * @return void
     *
     * @since 1.0.0
     */
    protected function registerCoreTabs(): void
    {
        $root = dirname(__DIR__, 2);

        // Controller-backed tabs: key => controller class.
        $controllers = [
            'employees'   => EmployeesTabController::class,
            'departments' => DepartmentsTabController::class,
            'positions'   => PositionsTabController::class,
            'grades'      => GradesTabController::class,
            'benefits'    => BenefitsTabController::class,
            'leave_types' => LeaveTypesTabController::class,
            'roles'       => RolesTabController::class,
            'teams'       => TeamsTabController::class,
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
            $key = $tab['key'];
            $controllerClass = isset($controllers[$key]) ? $controllers[$key] : null;
            $this->registerTab(new TabRegistration(
                $key,
                $tab['label'],
                $tab['security'],
                $controllerClass,
                $priority,
                0,
                $controllerClass === null ? ($root . '/pages/' . $key . '.php') : null
            ));
            $priority += 10;
        }
    }
}