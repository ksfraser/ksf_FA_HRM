<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Hooks;

/**
 * HRM Install Hook
 *
 * Handles module lifecycle: installation, activation, deactivation,
 * and uninstallation of HRM database tables and configuration.
 *
 * Table ownership:
 * - HRM core: 0_hrm_departments, 0_hrm_teams, 0_hrm_roles, 0_hrm_role_dictionary,
 *   0_hrm_positions, 0_hrm_grades, 0_hrm_contacts_employment
 * - Employment detail: 0_hrm_contacts_pii, 0_hrm_contacts_banking, 0_hrm_dependent_details
 * - Compensation: 0_hrm_pay_periods, 0_hrm_pay_rate_history, 0_hrm_work_assignments
 * - Benefits: 0_ksf_hrm_benefits, 0_ksf_hrm_employee_benefits
 * - Payroll: 0_ksf_hrm_payroll, 0_ksf_hrm_payroll_entries
 * - Pay elements: 0_hrm_pay_elements, 0_hrm_salary_structure
 * - Separation: 0_hrm_separation_reasons
 * - Employment status: 0_ksf_hrm_employment_status
 *
 * NOT owned by HRM (moved to ksf_FA_Leave):
 * - 0_leave_types, 0_leave_requests, 0_leave_balances, 0_leave_bank_config,
 *   0_leave_banks, 0_leave_transactions, 0_leave_holidays, etc.
 *
 * NOT owned by HRM (moved to ksf_FA_Recruitment):
 * - 0_recruit_job_openings, 0_recruit_job_applications, 0_recruit_interviews,
 *   0_recruit_role_grades, 0_recruit_position_grades, 0_recruit_grade_approvals,
 *   0_recruit_offers
 *
 * @package ksfraser\FrontAccounting\HRM
 * @since 1.0.0
 */
class InstallHook
{
    /**
     * Run on module installation.
     */
    public static function install(): void
    {
        self::createMenuItems();
        self::setDefaultPreferences();
    }

    /**
     * Run on module activation (enable).
     *
     * Uses consolidated install.sql for all HRM tables.
     */
    public static function activate($company, $check_only = true): bool
    {
        if (!file_exists(dirname(__FILE__) . '/../../sql/install.sql')) {
            return true;
        }

        $updates = array(
            'install.sql' => array('0_hrm_departments'),
        );

        return self::update_databases($company, $updates, $check_only);
    }

    /**
     * Register menu items.
     */
    private static function createMenuItems(): void
    {
        add_module_extensions_menu_item(
            'ksf_hrm',
            _('HRM'),
            'HRM',
            null,
            FA_PERMISSION_READ
        );
    }

    /**
     * Set default company preferences for HRM.
     */
    private static function setDefaultPreferences(): void
    {
        $defaults = [
            'ksf_salary_expense_gl' => 'G01',
            'ksf_ot_expense_gl' => 'O01',
            'ksf_year_hours' => '2080',
            'ksf_week_hours' => '40',
            'ksf_ot_enabled' => '1',
        ];

        foreach ($defaults as $pref => $value) {
            if (get_company_pref($pref) === '') {
                set_company_pref($pref, $value);
            }
        }
    }

    /**
     * Run on module deactivation/uninstall.
     *
     * Drops HRM-owned tables. Does NOT drop shared tables or
     * tables owned by other modules (Leave, Recruitment).
     */
    public static function uninstall(): void
    {
        $tables = [
            'hrm_contacts_employment',
            'hrm_contacts_pii',
            'hrm_contacts_banking',
            'hrm_dependent_details',
            'hrm_departments',
            'hrm_teams',
            'hrm_roles',
            'hrm_role_dictionary',
            'hrm_positions',
            'hrm_grades',
            'hrm_work_assignments',
            'hrm_pay_rate_history',
            'hrm_pay_periods',
            'hrm_pay_elements',
            'hrm_salary_structure',
            'hrm_separation_reasons',
            'ksf_hrm_benefits',
            'ksf_hrm_employee_benefits',
            'ksf_hrm_payroll',
            'ksf_hrm_payroll_entries',
            'ksf_hrm_employment_status',
        ];

        foreach ($tables as $table) {
            if (db_has_table(TB_PREF . $table)) {
                db_query("DROP TABLE " . TB_PREF . $table);
            }
        }

        // Remove CRM categories entries for employee types
        db_query("DELETE FROM " . TB_PREF . "crm_categories WHERE `type` = 'employee'");
    }
}
