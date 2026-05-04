-- fa_contacts_employment table
-- Employment details (links person to department, position, grade)
-- HRM owns this table

CREATE TABLE IF NOT EXISTS `fa_contacts_employment` (
    `employment_id` INT(11) NOT NULL AUTO_INCREMENT,
    `person_id` INT(11) NOT NULL COMMENT 'FK to 0_crm_persons.id (type=Employee)',
    `employee_code` VARCHAR(20) DEFAULT NULL COMMENT 'e.g., EMP001',
    `department_id` INT(11) DEFAULT NULL,
    `position_id` INT(11) DEFAULT NULL,
    `grade_id` INT(11) DEFAULT NULL,
    `employment_type` INT(11) DEFAULT NULL COMMENT '1=Full-time, 2=Part-time, 3=Contract, 4=Temporary',
    `hire_date` DATE DEFAULT NULL,
    `probation_end_date` DATE DEFAULT NULL,
    `confirmation_date` DATE DEFAULT NULL,
    `termination_date` DATE DEFAULT NULL,
    `separation_reason_id` INT(11) DEFAULT NULL,
    `salary_amount` DECIMAL(15,2) DEFAULT 0 COMMENT 'Personal override (NULL = use structure)',
    `login_id` VARCHAR(100) DEFAULT NULL COMMENT 'Links to FA user',
    `reports_to_person_id` INT(11) DEFAULT NULL COMMENT 'Manager (another person)',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`employment_id`),
    UNIQUE KEY `idx_employee_code` (`employee_code`),
    UNIQUE KEY `idx_person` (`person_id`),
    KEY `idx_department` (`department_id`),
    KEY `idx_position` (`position_id`),
    KEY `idx_grade` (`grade_id`),
    KEY `idx_active` (`is_active`),
    CONSTRAINT `fk_employment_person` FOREIGN KEY (`person_id`) REFERENCES `0_crm_persons`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_employment_manager` FOREIGN KEY (`reports_to_person_id`) REFERENCES `0_crm_persons`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
