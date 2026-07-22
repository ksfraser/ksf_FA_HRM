-- fa_dependent_details table
-- Additional info for dependents (for benefits)
-- Links to 0_crm_persons (the dependent) + employee_person_id (the employee)
-- HRM owns this table

CREATE TABLE IF NOT EXISTS `fa_dependent_details` (
    `dependent_id` INT(11) NOT NULL AUTO_INCREMENT,
    `person_id` INT(11) NOT NULL COMMENT 'FK to 0_crm_persons.id (the dependent)',
    `employee_person_id` INT(11) NOT NULL COMMENT 'FK to 0_crm_persons.id (the employee)',
    `relationship` VARCHAR(50) DEFAULT NULL COMMENT 'Spouse, Child, Parent, etc.',
    `date_of_birth` DATE DEFAULT NULL,
    `gender` INT(11) DEFAULT NULL,
    `eligible_for_benefits` TINYINT(1) DEFAULT 1,
    `benefit_start_date` DATE DEFAULT NULL,
    `benefit_end_date` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`dependent_id`),
    UNIQUE KEY `idx_person` (`person_id`),
    KEY `idx_employee` (`employee_person_id`),
    CONSTRAINT `fk_dependent_person` FOREIGN KEY (`person_id`) REFERENCES `0_crm_persons`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_dependent_employee` FOREIGN KEY (`employee_person_id`) REFERENCES `0_crm_persons`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
