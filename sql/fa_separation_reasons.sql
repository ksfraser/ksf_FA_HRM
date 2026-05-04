-- fa_separation_reasons table
-- Termination reasons (HRM data)

CREATE TABLE IF NOT EXISTS `fa_separation_reasons` (
    `reason_id` INT(11) NOT NULL AUTO_INCREMENT,
    `description` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `sort_order` INT(11) DEFAULT 0,
    PRIMARY KEY (`reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default separation reasons
INSERT IGNORE INTO `fa_separation_reasons` (`description`, `sort_order`) VALUES
('Resignation', 1),
('Termination', 2),
('Retirement', 3),
('Layoff', 4),
('End of Contract', 5),
('Death', 6),
('Other', 99);
