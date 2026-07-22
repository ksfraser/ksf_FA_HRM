-- fa_departments table
-- Enhanced departments with manager linkage
-- (HRM owns department/position/grade, not CRM)

CREATE TABLE IF NOT EXISTS `fa_departments` (
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
    KEY `idx_manager` (`manager_person_id`),
    CONSTRAINT `fk_dept_manager` FOREIGN KEY (`manager_person_id`) REFERENCES `0_crm_persons`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
