-- fa_grades table
-- Salary grades with hierarchy
-- HRM owns this table

CREATE TABLE IF NOT EXISTS `fa_grades` (
    `grade_id` INT(11) NOT NULL AUTO_INCREMENT,
    `grade_name` VARCHAR(50) NOT NULL,
    `position_id` INT(11) DEFAULT NULL COMMENT 'Which position this grade belongs to',
    `grade_level` INT(11) DEFAULT 0 COMMENT '1, 2, 3...',
    `min_salary` DECIMAL(15,2) DEFAULT 0,
    `mid_salary` DECIMAL(15,2) DEFAULT 0,
    `max_salary` DECIMAL(15,2) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`grade_id`),
    KEY `idx_position` (`position_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
