-- fa_contacts_pii table
-- PII separated for GDPR compliance
-- Links to FA's 0_crm_persons table via person_id
-- HRM owns this table

CREATE TABLE IF NOT EXISTS `fa_contacts_pii` (
    `person_id` INT(11) NOT NULL,
    `gender` INT(11) DEFAULT NULL COMMENT '0=Male, 1=Female, 2=Other',
    `birth_date` DATE DEFAULT NULL,
    `nationality` VARCHAR(50) DEFAULT NULL,
    `national_id` VARCHAR(50) DEFAULT NULL COMMENT 'SSN, SIN, etc.',
    `passport` VARCHAR(50) DEFAULT NULL,
    `passport_expiry` DATE DEFAULT NULL,
    `marital_status` INT(11) DEFAULT NULL COMMENT '0=Single, 1=Married, etc.',
    `dependents_no` INT(11) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`person_id`),
    CONSTRAINT `fk_pii_person` FOREIGN KEY (`person_id`) REFERENCES `0_crm_persons`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
