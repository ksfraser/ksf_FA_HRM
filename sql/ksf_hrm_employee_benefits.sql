-- ksf_hrm_employee_benefits table
-- Links employees (contacts) to benefits

CREATE TABLE IF NOT EXISTS `ksf_hrm_employee_benefits` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `person_id` INT(11) NOT NULL COMMENT 'FK to 0_crm_persons',
    `benefit_id` INT(11) NOT NULL,
    `effective_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `custom_employer_rate` DECIMAL(5,2) DEFAULT NULL,
    `custom_employee_rate` DECIMAL(5,2) DEFAULT NULL,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_person` (`person_id`),
    KEY `idx_benefit` (`benefit_id`),
    CONSTRAINT `fk_emp_benefit_person` FOREIGN KEY (`person_id`) REFERENCES `0_crm_persons`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
