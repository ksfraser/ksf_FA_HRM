-- ============================================================================
-- ksf_FA_HRM Module Installation SQL
-- ============================================================================
-- Consolidated from individual SQL files. Uses 0_ prefix for FA db_import().
-- ============================================================================

-- Departments
CREATE TABLE IF NOT EXISTS `0_fa_departments` (
    `department_id` INT(11) NOT NULL AUTO_INCREMENT,
    `department_code` VARCHAR(20) DEFAULT NULL,
    `department_name` VARCHAR(100) NOT NULL,
    `manager_person_id` INT(11) DEFAULT NULL,
    `parent_department_id` INT(11) DEFAULT NULL,
    `cost_center_id` INT(11) DEFAULT NULL,
    `description` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`department_id`),
    KEY `idx_parent` (`parent_department_id`),
    KEY `idx_manager` (`manager_person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Positions
CREATE TABLE IF NOT EXISTS `0_fa_positions` (
    `position_id` INT(11) NOT NULL AUTO_INCREMENT,
    `position_code` VARCHAR(20) DEFAULT NULL,
    `position_name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`position_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grades
CREATE TABLE IF NOT EXISTS `0_fa_grades` (
    `grade_id` INT(11) NOT NULL AUTO_INCREMENT,
    `grade_code` VARCHAR(20) DEFAULT NULL,
    `grade_name` VARCHAR(100) NOT NULL,
    `min_salary` DECIMAL(15,2) DEFAULT 0,
    `max_salary` DECIMAL(15,2) DEFAULT 0,
    `description` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`grade_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pay Elements (earnings, deductions, contributions)
CREATE TABLE IF NOT EXISTS `0_fa_pay_elements` (
    `element_id` INT(11) NOT NULL AUTO_INCREMENT,
    `element_code` VARCHAR(20) NOT NULL,
    `element_name` VARCHAR(100) NOT NULL,
    `category` VARCHAR(20) NOT NULL COMMENT 'earning|deduction|contribution',
    `calculation_type` VARCHAR(20) DEFAULT 'fixed' COMMENT 'fixed|percentage|formula',
    `default_value` DECIMAL(15,2) DEFAULT 0,
    `gl_account_code` VARCHAR(20) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`element_id`),
    UNIQUE KEY `idx_code` (`element_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Salary Structure (links grade to pay elements)
CREATE TABLE IF NOT EXISTS `0_fa_salary_structure` (
    `structure_id` INT(11) NOT NULL AUTO_INCREMENT,
    `grade_id` INT(11) NOT NULL,
    `element_id` INT(11) NOT NULL,
    `default_amount` DECIMAL(15,2) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`structure_id`),
    KEY `idx_grade` (`grade_id`),
    KEY `idx_element` (`element_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Separation Reasons
CREATE TABLE IF NOT EXISTS `0_fa_separation_reasons` (
    `reason_id` INT(11) NOT NULL AUTO_INCREMENT,
    `reason_code` VARCHAR(20) NOT NULL,
    `reason_name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employment Details (links person to department, position, grade)
CREATE TABLE IF NOT EXISTS `0_fa_contacts_employment` (
    `employment_id` INT(11) NOT NULL AUTO_INCREMENT,
    `person_id` INT(11) NOT NULL COMMENT 'FK to 0_crm_persons.id',
    `employee_code` VARCHAR(20) DEFAULT NULL COMMENT 'e.g., EMP001',
    `department_id` INT(11) DEFAULT NULL,
    `position_id` INT(11) DEFAULT NULL,
    `grade_id` INT(11) DEFAULT NULL,
    `employment_type` INT(11) DEFAULT 1 COMMENT '1=Full-time, 2=Part-time, 3=Contract, 4=Temporary',
    `hire_date` DATE DEFAULT NULL,
    `probation_end_date` DATE DEFAULT NULL,
    `confirmation_date` DATE DEFAULT NULL,
    `termination_date` DATE DEFAULT NULL,
    `separation_reason_id` INT(11) DEFAULT NULL,
    `salary_amount` DECIMAL(15,2) DEFAULT 0 COMMENT 'Personal override',
    `login_id` VARCHAR(100) DEFAULT NULL COMMENT 'Links to FA user',
    `reports_to_person_id` INT(11) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`employment_id`),
    UNIQUE KEY `idx_employee_code` (`employee_code`),
    UNIQUE KEY `idx_person` (`person_id`),
    KEY `idx_department` (`department_id`),
    KEY `idx_position` (`position_id`),
    KEY `idx_grade` (`grade_id`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PII (Personally Identifiable Information) - separated for security
CREATE TABLE IF NOT EXISTS `0_fa_contacts_pii` (
    `pii_id` INT(11) NOT NULL AUTO_INCREMENT,
    `person_id` INT(11) NOT NULL COMMENT 'FK to 0_crm_persons.id',
    `date_of_birth` DATE DEFAULT NULL,
    `gender` VARCHAR(20) DEFAULT NULL,
    `national_id` VARCHAR(50) DEFAULT NULL,
    `passport_number` VARCHAR(50) DEFAULT NULL,
    `tax_number` VARCHAR(50) DEFAULT NULL,
    `marital_status` VARCHAR(20) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`pii_id`),
    UNIQUE KEY `idx_person` (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Banking Details
CREATE TABLE IF NOT EXISTS `0_fa_contacts_banking` (
    `banking_id` INT(11) NOT NULL AUTO_INCREMENT,
    `person_id` INT(11) NOT NULL COMMENT 'FK to 0_crm_persons.id',
    `bank_name` VARCHAR(100) DEFAULT NULL,
    `branch_name` VARCHAR(100) DEFAULT NULL,
    `account_number` VARCHAR(50) DEFAULT NULL,
    `account_type` VARCHAR(20) DEFAULT NULL,
    `routing_number` VARCHAR(50) DEFAULT NULL,
    `is_primary` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`banking_id`),
    KEY `idx_person` (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dependent Details
CREATE TABLE IF NOT EXISTS `0_fa_dependent_details` (
    `dependent_id` INT(11) NOT NULL AUTO_INCREMENT,
    `person_id` INT(11) NOT NULL COMMENT 'FK to 0_crm_persons.id (employee)',
    `dependent_name` VARCHAR(100) NOT NULL,
    `relationship` VARCHAR(50) DEFAULT NULL,
    `date_of_birth` DATE DEFAULT NULL,
    `is_beneficiary` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`dependent_id`),
    KEY `idx_person` (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Benefits
CREATE TABLE IF NOT EXISTS `0_ksf_hrm_benefits` (
    `benefit_id` INT(11) NOT NULL AUTO_INCREMENT,
    `benefit_code` VARCHAR(20) NOT NULL,
    `benefit_name` VARCHAR(100) NOT NULL,
    `benefit_type` VARCHAR(50) DEFAULT NULL,
    `employer_contribution` DECIMAL(15,2) DEFAULT 0,
    `employee_contribution` DECIMAL(15,2) DEFAULT 0,
    `gl_account_code` VARCHAR(20) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`benefit_id`),
    UNIQUE KEY `idx_code` (`benefit_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employee Benefits (assignments)
CREATE TABLE IF NOT EXISTS `0_ksf_hrm_employee_benefits` (
    `assignment_id` INT(11) NOT NULL AUTO_INCREMENT,
    `person_id` INT(11) NOT NULL COMMENT 'FK to 0_crm_persons.id',
    `benefit_id` INT(11) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE DEFAULT NULL,
    `employee_contribution` DECIMAL(15,2) DEFAULT 0 COMMENT 'Override if needed',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`assignment_id`),
    KEY `idx_person` (`person_id`),
    KEY `idx_benefit` (`benefit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payroll
CREATE TABLE IF NOT EXISTS `0_ksf_hrm_payroll` (
    `payroll_id` INT(11) NOT NULL AUTO_INCREMENT,
    `person_id` INT(11) NOT NULL COMMENT 'FK to 0_crm_persons',
    `pay_period_start` DATE NOT NULL,
    `pay_period_end` DATE NOT NULL,
    `gross_pay` DECIMAL(15,2) DEFAULT 0,
    `total_deductions` DECIMAL(15,2) DEFAULT 0,
    `net_pay` DECIMAL(15,2) DEFAULT 0,
    `pay_date` DATE NOT NULL,
    `status` VARCHAR(20) DEFAULT 'Draft',
    `gl_posted` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`payroll_id`),
    KEY `idx_person` (`person_id`),
    KEY `idx_period` (`pay_period_start`, `pay_period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payroll Entries (line items per payroll run)
CREATE TABLE IF NOT EXISTS `0_ksf_hrm_payroll_entries` (
    `entry_id` INT(11) NOT NULL AUTO_INCREMENT,
    `payroll_id` INT(11) NOT NULL,
    `element_id` INT(11) NOT NULL,
    `amount` DECIMAL(15,2) DEFAULT 0,
    `note` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`entry_id`),
    KEY `idx_payroll` (`payroll_id`),
    KEY `idx_element` (`element_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Leave Balances
CREATE TABLE IF NOT EXISTS `0_ksf_hrm_leave_balances` (
    `balance_id` INT(11) NOT NULL AUTO_INCREMENT,
    `person_id` INT(11) NOT NULL COMMENT 'FK to 0_crm_persons.id',
    `leave_type` VARCHAR(50) NOT NULL COMMENT 'Annual, Sick, Personal, etc.',
    `total_days` DECIMAL(5,1) DEFAULT 0,
    `used_days` DECIMAL(5,1) DEFAULT 0,
    `year` INT(4) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`balance_id`),
    UNIQUE KEY `idx_person_type_year` (`person_id`, `leave_type`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
