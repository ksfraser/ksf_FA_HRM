<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\App;

use ksfraser\FrontAccounting\Common\App\AbstractAppShell;
use ksfraser\FrontAccounting\Common\App\TabRegistration;
use ksfraser\FrontAccounting\HRM\Controller\DepartmentsTabController;

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
     * Departments is controller-backed (SRP pilot).
     *
     * @return void
     *
     * @since 1.0.0
     */
    protected function registerCoreTabs(): void
    {
        $root = dirname(__DIR__, 2);

        $tabs = [
            ['key' => 'employees',   'label' => 'Employees',   'security' => 'SA_HRM_EMPLOYEE'],
            ['key' => 'departments', 'label' => 'Departments', 'security' => 'SA_HRM_DEPARTMENT'],
            ['key' => 'positions',   'label' => 'Positions',   'security' => 'SA_ksf_FA_HRMMANAGE'],
            ['key' => 'grades',      'label' => 'Grades',      'security' => 'SA_ksf_FA_HRMMANAGE'],
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
            $controllerClass = ($key === 'departments') ? DepartmentsTabController::class : null;
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