-- ============================================================================
-- ksf_FA_HRM Module Installation SQL
-- ============================================================================
-- Consolidated from individual SQL files. Uses 0_ prefix for FA db_import().
-- ============================================================================

-- Departments
CREATE TABLE IF NOT EXISTS `0_hrm_departments` (
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

-- Role Dictionary (global master list of role types)
CREATE TABLE IF NOT EXISTS `0_hrm_role_dictionary` (
    `role_dict_id` INT(11) NOT NULL AUTO_INCREMENT,
    `role_name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`role_dict_id`),
    UNIQUE KEY `idx_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Department Roles (cloned from dictionary into each department)
CREATE TABLE IF NOT EXISTS `0_hrm_roles` (
    `role_id` INT(11) NOT NULL AUTO_INCREMENT,
    `department_id` INT(11) NOT NULL,
    `role_dict_id` INT(11) DEFAULT NULL COMMENT 'Source from dictionary',
    `role_name` VARCHAR(100) NOT NULL COMMENT 'Can be customized per dept',
    `description` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`role_id`),
    KEY `idx_department` (`department_id`),
    KEY `idx_dict` (`role_dict_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teams (recursive, within a department)
CREATE TABLE IF NOT EXISTS `0_hrm_teams` (
    `team_id` INT(11) NOT NULL AUTO_INCREMENT,
    `department_id` INT(11) NOT NULL,
    `parent_team_id` INT(11) DEFAULT NULL COMMENT 'Self-referential for sub-teams',
    `team_code` VARCHAR(20) NOT NULL COMMENT 'Short code for position codes (e.g., SUP, DEV)',
    `team_name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`team_id`),
    UNIQUE KEY `idx_dept_code` (`department_id`, `team_code`),
    KEY `idx_parent` (`parent_team_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Positions (role filled within a team; code = DEPT-TEAM-###)
CREATE TABLE IF NOT EXISTS `0_hrm_positions` (
    `position_id` INT(11) NOT NULL AUTO_INCREMENT,
    `position_code` VARCHAR(50) NOT NULL COMMENT 'Generated: DEPT-TEAM-###',
    `department_id` INT(11) NOT NULL,
    `team_id` INT(11) DEFAULT NULL,
    `role_id` INT(11) NOT NULL,
    `position_number` INT(11) NOT NULL DEFAULT 1 COMMENT 'Sequential within dept-team',
    `description` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`position_id`),
    UNIQUE KEY `idx_code` (`position_code`),
    KEY `idx_department` (`department_id`),
    KEY `idx_team` (`team_id`),
    KEY `idx_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grades
CREATE TABLE IF NOT EXISTS `0_hrm_grades` (
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

-- Employee work assignment (links employment to position + salary details)
CREATE TABLE IF NOT EXISTS `0_hrm_work_assignments` (
    `assignment_id` INT(11) NOT NULL AUTO_INCREMENT,
    `employment_id` INT(11) NOT NULL COMMENT 'FK to 0_hrm_contacts_employment',
    `position_id` INT(11) NOT NULL,
    `grade_id` INT(11) DEFAULT NULL,
    `salary_amount` DECIMAL(15,2) DEFAULT 0 COMMENT 'Annual/monthly salary',
    `hourly_rate` DECIMAL(10,4) DEFAULT 0 COMMENT 'Calculated or overridden',
    `pay_frequency` VARCHAR(20) DEFAULT 'Monthly' COMMENT 'Annual|Monthly|BiWeekly|Weekly|Hourly',
    `effective_date` DATE NOT NULL,
    `end_date` DATE DEFAULT NULL,
    `is_current` TINYINT(1) DEFAULT 1,
    `reason` VARCHAR(100) DEFAULT NULL COMMENT 'New Hire, Promotion, Transfer, Raise',
    `approved_by_person_id` INT(11) DEFAULT NULL,
    `approval_status` VARCHAR(20) DEFAULT 'Approved' COMMENT 'Pending|Approved|Rejected',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`assignment_id`),
    KEY `idx_employment` (`employment_id`),
    KEY `idx_position` (`position_id`),
    KEY `idx_current` (`is_current`),
    KEY `idx_effective` (`effective_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pay rate history (track raises with effective dates for overlap calculation)
CREATE TABLE IF NOT EXISTS `0_hrm_pay_rate_history` (
    `rate_id` INT(11) NOT NULL AUTO_INCREMENT,
    `employment_id` INT(11) NOT NULL,
    `assignment_id` INT(11) DEFAULT NULL,
    `old_salary` DECIMAL(15,2) DEFAULT 0,
    `new_salary` DECIMAL(15,2) DEFAULT 0,
    `old_hourly_rate` DECIMAL(10,4) DEFAULT 0,
    `new_hourly_rate` DECIMAL(10,4) DEFAULT 0,
    `effective_date` DATE NOT NULL,
    `reason` VARCHAR(100) DEFAULT NULL,
    `approved_by_person_id` INT(11) DEFAULT NULL,
    `approval_status` VARCHAR(20) DEFAULT 'Approved',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`rate_id`),
    KEY `idx_employment` (`employment_id`),
    KEY `idx_effective` (`effective_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pay periods
CREATE TABLE IF NOT EXISTS `0_hrm_pay_periods` (
    `period_id` INT(11) NOT NULL AUTO_INCREMENT,
    `period_name` VARCHAR(50) NOT NULL,
    `period_start` DATE NOT NULL,
    `period_end` DATE NOT NULL,
    `pay_date` DATE NOT NULL,
    `frequency` VARCHAR(20) NOT NULL COMMENT 'Weekly|BiWeekly|Monthly|Quarterly',
    `status` VARCHAR(20) DEFAULT 'Open' COMMENT 'Open|Processing|Closed|Paid',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`period_id`),
    KEY `idx_dates` (`period_start`, `period_end`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pay Elements (earnings, deductions, contributions)
CREATE TABLE IF NOT EXISTS `0_hrm_pay_elements` (
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
CREATE TABLE IF NOT EXISTS `0_hrm_salary_structure` (
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
CREATE TABLE IF NOT EXISTS `0_hrm_separation_reasons` (
    `reason_id` INT(11) NOT NULL AUTO_INCREMENT,
    `reason_code` VARCHAR(20) NOT NULL,
    `reason_name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employment Details (links person to department, position, grade)
CREATE TABLE IF NOT EXISTS `0_hrm_contacts_employment` (
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
CREATE TABLE IF NOT EXISTS `0_hrm_contacts_pii` (
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
CREATE TABLE IF NOT EXISTS `0_hrm_contacts_banking` (
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
CREATE TABLE IF NOT EXISTS `0_hrm_dependent_details` (
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

-- Employment Status Lookup
CREATE TABLE IF NOT EXISTS `0_ksf_hrm_employment_status` (
    `status_id` INT(11) NOT NULL AUTO_INCREMENT,
    `status_code` VARCHAR(20) NOT NULL,
    `status_name` VARCHAR(100) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`status_id`),
    UNIQUE KEY `idx_code` (`status_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role Dictionary seed data
INSERT IGNORE INTO `0_hrm_role_dictionary` (role_dict_id, role_name, description) VALUES
(1, 'Manager', 'Team or department manager'),
(2, 'Assistant Manager', 'Deputy to the manager'),
(3, 'Team Lead', 'Leads a small team'),
(4, 'Supervisor', 'Supervises operational staff'),
(5, 'Coordinator', 'Coordinates activities across teams'),
(6, 'Analyst', 'Data or business analysis'),
(7, 'Developer', 'Software development'),
(8, 'Engineer', 'Engineering or technical role'),
(9, 'Technician', 'Technical support or maintenance'),
(10, 'Administrator', 'Administrative support'),
(11, 'Officer', 'General officer role'),
(12, 'Specialist', 'Domain specialist'),
(13, 'Consultant', 'Advisory or consulting role'),
(14, 'Representative', 'Customer or vendor facing'),
(15, 'Agent', 'Operational agent'),
(16, 'Director', 'Senior leadership'),
(17, 'Executive', 'C-level or senior executive'),
(18, 'Intern', 'Temporary learning position'),
(19, 'Contractor', 'External contracted role'),
(20, 'Trainee', 'Entry-level training position');
