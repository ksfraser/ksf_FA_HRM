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
        self::createTables();
        self::createMenuItems();
        self::setDefaultPreferences();
    }

    private static function createTables(): void
    {
        // HRM uses the contact system from ksf_FA_CRM
        // These tables should already exist from ksf_FA_CRM install:
        // - fa_contacts_employment (employment details)
        // - fa_contacts_pii (PII separated)
        // - fa_contacts_banking (banking details)
        // - fa_dependent_details (benefit-specific dependent info)
        // - fa_departments, fa_positions, fa_grades
        // - fa_pay_elements, fa_salary_structure
        // - fa_separation_reasons

        // HRM-specific tables that extend the contact system:
        $tables = [
            'ksf_hrm_benefits' => "
                CREATE TABLE IF NOT EXISTS " . TB_PREF . "ksf_hrm_benefits (
                    benefit_id INT NOT NULL AUTO_INCREMENT,
                    benefit_name VARCHAR(100) NOT NULL,
                    benefit_code VARCHAR(20) UNIQUE NOT NULL,
                    benefit_type VARCHAR(50),
                    employer_rate DECIMAL(5,2),
                    employee_rate DECIMAL(5,2),
                    fixed_amount DECIMAL(10,2),
                    calculation_period VARCHAR(20) DEFAULT 'Monthly',
                    is_percentage_based TINYINT DEFAULT 1,
                    gl_code_expense VARCHAR(20),
                    gl_code_liability VARCHAR(20),
                    provider VARCHAR(100),
                    description TEXT,
                    is_active TINYINT DEFAULT 1,
                    is_mandatory TINYINT DEFAULT 0,
                    is_tax_deductible TINYINT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (benefit_id),
                    KEY idx_code (benefit_code)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'ksf_hrm_employee_benefits' => "
                CREATE TABLE IF NOT EXISTS " . TB_PREF . "ksf_hrm_employee_benefits (
                    id INT NOT NULL AUTO_INCREMENT,
                    person_id INT(11) NOT NULL COMMENT 'FK to 0_crm_persons',
                    benefit_id INT NOT NULL,
                    effective_date DATE DEFAULT NULL,
                    end_date DATE DEFAULT NULL,
                    custom_employer_rate DECIMAL(5,2) DEFAULT NULL,
                    custom_employee_rate DECIMAL(5,2) DEFAULT NULL,
                    notes TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_person (person_id),
                    KEY idx_benefit (benefit_id),
                    CONSTRAINT `fk_emp_benefit_person` FOREIGN KEY (`person_id`) REFERENCES `" . TB_PREF . "crm_persons`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'ksf_hrm_payroll' => "
                CREATE TABLE IF NOT EXISTS " . TB_PREF . "ksf_hrm_payroll (
                    payroll_id INT NOT NULL AUTO_INCREMENT,
                    person_id INT(11) NOT NULL COMMENT 'FK to 0_crm_persons (employee)',
                    pay_period_start DATE NOT NULL,
                    pay_period_end DATE NOT NULL,
                    gross_pay DECIMAL(15,2) DEFAULT 0,
                    total_deductions DECIMAL(15,2) DEFAULT 0,
                    net_pay DECIMAL(15,2) DEFAULT 0,
                    pay_date DATE NOT NULL,
                    status VARCHAR(20) DEFAULT 'Draft',
                    gl_posted TINYINT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (payroll_id),
                    KEY idx_person (person_id),
                    KEY idx_period (pay_period_start, pay_period_end),
                    CONSTRAINT `fk_payroll_person` FOREIGN KEY (`person_id`) REFERENCES `" . TB_PREF . "crm_persons`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'ksf_hrm_payroll_entries' => "
                CREATE TABLE IF NOT EXISTS " . TB_PREF . "ksf_hrm_payroll_entries (
                    entry_id INT NOT NULL AUTO_INCREMENT,
                    payroll_id INT NOT NULL,
                    element_id INT(11) NOT NULL,
                    amount DECIMAL(15,2) DEFAULT 0,
                    note VARCHAR(255) DEFAULT NULL,
                    PRIMARY KEY (entry_id),
                    KEY idx_payroll (payroll_id),
                    KEY idx_element (element_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'ksf_hrm_leave_balances' => "
                CREATE TABLE IF NOT EXISTS " . TB_PREF . "ksf_hrm_leave_balances (
                    balance_id INT NOT NULL AUTO_INCREMENT,
                    person_id INT(11) NOT NULL,
                    leave_type_id INT(11) NOT NULL,
                    year INT(4) NOT NULL,
                    allocated_days DECIMAL(5,2) DEFAULT 0,
                    used_days DECIMAL(5,2) DEFAULT 0,
                    carried_over DECIMAL(5,2) DEFAULT 0,
                    PRIMARY KEY (balance_id),
                    UNIQUE KEY idx_person_year_type (person_id, year, leave_type_id),
                    CONSTRAINT `fk_leave_person` FOREIGN KEY (`person_id`) REFERENCES `" . TB_PREF . "crm_persons`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];

        foreach ($tables as $table => $sql) {
            if (!db_has_table(TB_PREF . $table)) {
                db_query($sql, "Failed to create $table");
            }
        }
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
    }
}