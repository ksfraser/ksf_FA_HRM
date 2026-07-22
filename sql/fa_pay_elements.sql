-- fa_pay_elements table
-- Salary components (earnings/deductions)
-- Defined in ksf_FA_HRM (not CRM) as HRM owns payroll

CREATE TABLE IF NOT EXISTS `fa_pay_elements` (
    `element_id` INT(11) NOT NULL AUTO_INCREMENT,
    `element_name` VARCHAR(100) NOT NULL,
    `element_code` VARCHAR(20) DEFAULT NULL,
    `is_deduction` TINYINT(1) DEFAULT 0 COMMENT '0=Earning, 1=Deduction',
    `is_taxable` TINYINT(1) DEFAULT 0,
    `affects_gross` TINYINT(1) DEFAULT 1,
    `account_code` VARCHAR(20) DEFAULT NULL COMMENT 'GL account',
    `element_category` INT(11) DEFAULT 0,
    `display_order` INT(11) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`element_id`),
    KEY `idx_code` (`element_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default pay elements (HRM data - earnings)
INSERT IGNORE INTO `fa_pay_elements` (`element_name`, `element_code`, `is_deduction`, `is_taxable`, `affects_gross`, `display_order`) VALUES
('Basic Salary', 'BASIC', 0, 1, 1, 10),
('House Rent Allowance', 'HRA', 0, 1, 1, 20),
('Transport Allowance', 'TA', 0, 1, 1, 30),
('Medical Allowance', 'MA', 0, 1, 1, 40),
('Performance Bonus', 'BONUS', 0, 1, 1, 50),
('Overtime Pay', 'OT', 0, 1, 1, 60);

-- Default pay elements (deductions)
INSERT IGNORE INTO `fa_pay_elements` (`element_name`, `element_code`, `is_deduction`, `is_taxable`, `affects_gross`, `display_order`) VALUES
('Income Tax', 'TAX', 1, 0, 0, 10),
('Social Security', 'SS', 1, 0, 0, 20),
('Health Insurance', 'HI', 1, 0, 0, 30),
('Retirement Fund', 'RF', 1, 0, 0, 40);
