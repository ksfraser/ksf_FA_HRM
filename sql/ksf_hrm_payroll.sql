-- ksf_hrm_payroll table

CREATE TABLE IF NOT EXISTS `ksf_hrm_payroll` (
    `payroll_id` INT(11) NOT NULL AUTO_INCREMENT,
    `person_id` INT(11) NOT NULL COMMENT 'FK to 0_crm_persons (employee)',
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
    KEY `idx_period` (`pay_period_start`, `pay_period_end`),
    CONSTRAINT `fk_payroll_person` FOREIGN KEY (`person_id`) REFERENCES `0_crm_persons`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ksf_hrm_payroll_entries table

CREATE TABLE IF NOT EXISTS `ksf_hrm_payroll_entries` (
    `entry_id` INT(11) NOT NULL AUTO_INCREMENT,
    `payroll_id` INT(11) NOT NULL,
    `element_id` INT(11) NOT NULL,
    `amount` DECIMAL(15,2) DEFAULT 0,
    `note` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`entry_id`),
    KEY `idx_payroll` (`payroll_id`),
    KEY `idx_element` (`element_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
