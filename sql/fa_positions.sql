-- fa_positions table
-- Job positions (shared by HRM + Recruitment + CRM)
-- HRM owns this table

CREATE TABLE IF NOT EXISTS `fa_positions` (
    `position_id` INT(11) NOT NULL AUTO_INCREMENT,
    `position_name` VARCHAR(100) NOT NULL,
    `position_code` VARCHAR(20) DEFAULT NULL,
    `job_class_id` INT(11) DEFAULT NULL,
    `basic_amount` DECIMAL(15,2) DEFAULT 0 COMMENT 'Base salary for this position',
    `description` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`position_id`),
    KEY `idx_code` (`position_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
