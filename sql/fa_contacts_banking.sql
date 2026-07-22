-- fa_contacts_banking table
-- Banking details for contacts (ACH/PAD/refunds)
-- Reusable for employees AND customers
-- HRM owns this table

CREATE TABLE IF NOT EXISTS `fa_contacts_banking` (
    `banking_id` INT(11) NOT NULL AUTO_INCREMENT,
    `person_id` INT(11) NOT NULL COMMENT 'FK to 0_crm_persons.id',
    `bank_name` VARCHAR(100) DEFAULT NULL,
    `bank_branch` VARCHAR(100) DEFAULT NULL,
    `account_number` VARCHAR(50) DEFAULT NULL,
    `routing_number` VARCHAR(50) DEFAULT NULL COMMENT 'ACH routing number',
    `account_type` VARCHAR(20) DEFAULT NULL COMMENT 'Checking, Savings, etc.',
    `is_primary` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`banking_id`),
    UNIQUE KEY `idx_person_primary` (`person_id`, `is_primary`),
    KEY `idx_person` (`person_id`),
    CONSTRAINT `fk_banking_person` FOREIGN KEY (`person_id`) REFERENCES `0_crm_persons`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
