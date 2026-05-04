-- ksf_hrm_benefits table
-- HRM-specific benefits table

CREATE TABLE IF NOT EXISTS `ksf_hrm_benefits` (
    `benefit_id` INT(11) NOT NULL AUTO_INCREMENT,
    `benefit_name` VARCHAR(100) NOT NULL,
    `benefit_code` VARCHAR(20) UNIQUE NOT NULL,
    `benefit_type` VARCHAR(50),
    `employer_rate` DECIMAL(5,2),
    `employee_rate` DECIMAL(5,2),
    `fixed_amount` DECIMAL(10,2),
    `calculation_period` VARCHAR(20) DEFAULT 'Monthly',
    `is_percentage_based` TINYINT(1) DEFAULT 1,
    `gl_code_expense` VARCHAR(20),
    `gl_code_liability` VARCHAR(20),
    `provider` VARCHAR(100),
    `description` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `is_mandatory` TINYINT(1) DEFAULT 0,
    `is_tax_deductible` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`benefit_id`),
    KEY `idx_code` (`benefit_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
