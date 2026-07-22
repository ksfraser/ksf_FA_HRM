-- fa_salary_structure table
-- Position × Grade × Element
-- (Salary structure is owned by HRM, not CRM)

CREATE TABLE IF NOT EXISTS `fa_salary_structure` (
    `structure_id` INT(11) NOT NULL AUTO_INCREMENT,
    `position_id` INT(11) NOT NULL,
    `grade_id` INT(11) NOT NULL,
    `element_id` INT(11) NOT NULL COMMENT 'Links to fa_pay_elements',
    `pay_amount` DECIMAL(15,2) DEFAULT 0,
    `formula` TEXT COMMENT 'For calculated fields',
    `effective_from` DATE DEFAULT NULL,
    `effective_to` DATE DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`structure_id`),
    KEY `idx_position_grade` (`position_id`, `grade_id`),
    KEY `idx_element` (`element_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
