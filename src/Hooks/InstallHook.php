<?php

declare(strict_types=1);

namespace Ksf\FA\HRM\Hooks;

/**
 * HRM Install Hook
 * Links to ksf_FA_CRM's unified contact system:
 * - Employees are contacts (0_crm_persons with type='employee')
 * - Emergency contacts are contacts (type='employee', action='emergency')
 * - Dependents are contacts (type='employee', action='dependent')
 * - Employment details in fa_contacts_employment
 * - PII in fa_contacts_pii
 * - Banking in fa_contacts_banking
 */
class InstallHook
{
    public static function install(): void
    {
        self::createMenuItems();
        self::setDefaultPreferences();
    }

    public static function activate($company, $check_only=true): bool
    {
        // FA's update_databases handles multiple SQL files automatically
        // Files are processed in dependency order: shared tables first, then HRM-specific
        $updates = array(
            // Shared tables (owned by HRM, used by CRM/Recruitment)
            'sql/fa_contacts_pii.sql' => array('ksf_FA_HRM'),
            'sql/fa_contacts_banking.sql' => array('ksf_FA_HRM'),
            'sql/fa_contacts_employment.sql' => array('ksf_FA_HRM'),
            'sql/fa_dependent_details.sql' => array('ksf_FA_HRM'),
            'sql/fa_departments.sql' => array('ksf_FA_HRM'),
            'sql/fa_positions.sql' => array('ksf_FA_HRM'),
            'sql/fa_grades.sql' => array('ksf_FA_HRM'),
            'sql/fa_pay_elements.sql' => array('ksf_FA_HRM'),
            'sql/fa_salary_structure.sql' => array('ksf_FA_HRM'),
            'sql/fa_separation_reasons.sql' => array('ksf_FA_HRM'),
            // HRM-specific tables
            'sql/ksf_hrm_benefits.sql' => array('ksf_FA_HRM'),
            'sql/ksf_hrm_employee_benefits.sql' => array('ksf_FA_HRM'),
            'sql/ksf_hrm_payroll.sql' => array('ksf_FA_HRM'),
            'sql/ksf_hrm_leave_balances.sql' => array('ksf_FA_HRM'),
            // CRM categories for employee/emergency/dependent contact types
            'sql/crm_categories.sql' => array('ksf_FA_HRM')
        );
        
        return self::update_databases($company, $updates, $check_only);
    }

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

    public static function uninstall(): void
    {
        // Drop HRM-specific tables
        $tables = [
            'ksf_hrm_benefits',
            'ksf_hrm_employee_benefits',
            'ksf_hrm_payroll',
            'ksf_hrm_payroll_entries',
            'ksf_hrm_leave_balances',
        ];

        foreach ($tables as $table) {
            if (db_has_table(TB_PREF . $table)) {
                db_query("DROP TABLE " . TB_PREF . $table);
            }
        }

        // Note: Shared tables (fa_*) are NOT dropped here
        // They may be used by CRM, Recruitment, etc.
        // Remove crm_categories entries for employee types
        db_query("DELETE FROM " . TB_PREF . "crm_categories WHERE `type` = 'employee'");
    }
}