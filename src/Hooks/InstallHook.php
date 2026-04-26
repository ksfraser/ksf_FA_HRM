<?php

declare(strict_types=1);

namespace Ksf\FA\HRM\Hooks;

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
        $tables = [
            'ksf_hrm_employees' => "
                CREATE TABLE IF NOT EXISTS " . TB_PREF . "ksf_hrm_employees (
                    id INT NOT NULL AUTO_INCREMENT,
                    employee_number VARCHAR(50) UNIQUE,
                    first_name VARCHAR(100) NOT NULL,
                    last_name VARCHAR(100) NOT NULL,
                    email VARCHAR(150),
                    phone VARCHAR(30),
                    department VARCHAR(100),
                    job_title VARCHAR(100),
                    status VARCHAR(20) DEFAULT 'Active',
                    hire_date DATE,
                    termination_date DATE,
                    manager_id INT,
                    career_manager_id INT,
                    operations_manager_id INT,
                    team_id INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY email (email),
                    KEY status (status),
                    KEY department (department)
                )",
            'ksf_hrm_grades' => "
                CREATE TABLE IF NOT EXISTS " . TB_PREF . "ksf_hrm_grades (
                    id INT NOT NULL AUTO_INCREMENT,
                    code VARCHAR(20) UNIQUE NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    min_salary DECIMAL(12,2),
                    max_salary DECIMAL(12,2),
                    min_hourly DECIMAL(10,4),
                    max_hourly DECIMAL(10,4),
                    description TEXT,
                    level VARCHAR(20),
                    active TINYINT DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id)
                )",
            'ksf_hrm_benefits' => "
                CREATE TABLE IF NOT EXISTS " . TB_PREF . "ksf_hrm_benefits (
                    id INT NOT NULL AUTO_INCREMENT,
                    name VARCHAR(100) NOT NULL,
                    code VARCHAR(20) UNIQUE NOT NULL,
                    type VARCHAR(50),
                    employer_rate DECIMAL(5,2),
                    employee_rate DECIMAL(5,2),
                    fixed_amount DECIMAL(10,2),
                    calculation_period VARCHAR(20) DEFAULT 'Monthly',
                    is_percentage_based TINYINT DEFAULT 1,
                    gl_code_expense VARCHAR(20),
                    gl_code_liability VARCHAR(20),
                    provider VARCHAR(100),
                    description TEXT,
                    active TINYINT DEFAULT 1,
                    is_mandatory TINYINT DEFAULT 0,
                    is_tax_deductible TINYINT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id)
                )",
            'ksf_hrm_compensation' => "
                CREATE TABLE IF NOT EXISTS " . TB_PREF . "ksf_hrm_compensation (
                    id INT NOT NULL AUTO_INCREMENT,
                    employee_id INT NOT NULL,
                    grade_id INT,
                    percent_of_grade DECIMAL(5,2),
                    annual_salary DECIMAL(12,2),
                    hourly_rate DECIMAL(10,4),
                    employee_type VARCHAR(20) DEFAULT 'Salary',
                    effective_date DATE,
                    end_date DATE,
                    ot_eligible TINYINT DEFAULT 0,
                    ot_multiplier DECIMAL(3,2) DEFAULT 1.5,
                    gl_code_salary VARCHAR(20) DEFAULT 'G01',
                    gl_code_overtime VARCHAR(20) DEFAULT 'O01',
                    benefits_package_id INT,
                    bonus_target DECIMAL(12,2),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY employee_id (employee_id),
                    KEY grade_id (grade_id)
                )",
            'ksf_hrm_emergency_contacts' => "
                CREATE TABLE IF NOT EXISTS " . TB_PREF . "ksf_hrm_emergency_contacts (
                    id INT NOT NULL AUTO_INCREMENT,
                    employee_id INT NOT NULL,
                    name VARCHAR(150) NOT NULL,
                    relationship VARCHAR(30),
                    phone VARCHAR(30),
                    alternate_phone VARCHAR(30),
                    email VARCHAR(150),
                    address TEXT,
                    is_primary TINYINT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY employee_id (employee_id)
                )",
            'ksf_hrm_dependents' => "
                CREATE TABLE IF NOT EXISTS " . TB_PREF . "ksf_hrm_dependents (
                    id INT NOT NULL AUTO_INCREMENT,
                    employee_id INT NOT NULL,
                    first_name VARCHAR(100) NOT NULL,
                    last_name VARCHAR(100) NOT NULL,
                    relationship VARCHAR(30),
                    date_of_birth DATE,
                    sin VARCHAR(20),
                    tax_credit_eligible TINYINT DEFAULT 1,
                    insurance_eligible TINYINT DEFAULT 0,
                    effective_date DATE,
                    end_date DATE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY employee_id (employee_id)
                )",
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
            'ksf_hrm_employees',
            'ksf_hrm_grades',
            'ksf_hrm_benefits',
            'ksf_hrm_compensation',
            'ksf_hrm_emergency_contacts',
            'ksf_hrm_dependents',
            'ksf_hrm_payroll',
        ];

        foreach ($tables as $table) {
            if (db_has_table(TB_PREF . $table)) {
                db_query("DROP TABLE " . TB_PREF . $table);
            }
        }
    }
}