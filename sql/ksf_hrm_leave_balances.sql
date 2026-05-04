-- ksf_hrm_leave_balances table

CREATE TABLE IF NOT EXISTS `ksf_hrm_leave_balances` (
    `balance_id` INT(11) NOT NULL AUTO_INCREMENT,
    `person_id` INT(11) NOT NULL,
    `leave_type_id` INT(11) NOT NULL,
    `year` INT(4) NOT NULL,
    `allocated_days` DECIMAL(5,2) DEFAULT 0,
    `used_days` DECIMAL(5,2) DEFAULT 0,
    `carried_over` DECIMAL(5,2) DEFAULT 0,
    PRIMARY KEY (`balance_id`),
    UNIQUE KEY `idx_person_year_type` (`person_id`, `year`, `leave_type_id`),
    CONSTRAINT `fk_leave_person` FOREIGN KEY (`person_id`) REFERENCES `0_crm_persons`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
