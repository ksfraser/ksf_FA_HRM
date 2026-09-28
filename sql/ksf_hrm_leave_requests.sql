-- ============================================================================
-- ksf_FA_HRM — Leave Approval Workflow (BR-HRM-01, FR-HRM-001-001/002)
-- ============================================================================
-- The missing root of leave management: real leave request rows plus an
-- append-only date-level day book. Balances alone cannot be approved.
--
-- Table naming uses literal `0_` so FA's install engine (db_import) rewrites
-- it to TB_PREF. Coexistence: ksf_FA_Leave ships its own `0_leave_*` masters
-- (types/balances/requests); the BR-HRM-01 vertical slice keeps its request
-- + consumption bookkeeping in HRM-owned tables (0_hrm_leave_*) and drives
-- them through the BR-COM-02 state machine (`leave_request.approval`).
-- ============================================================================

CREATE TABLE IF NOT EXISTS `0_hrm_leave_balances` (
    `balance_id` INT(11) NOT NULL AUTO_INCREMENT,
    `person_id` INT(11) NOT NULL,
    `leave_type_id` INT(11) NOT NULL,
    `year` INT(11) NOT NULL,
    `allocated_days` DECIMAL(5,2) NOT NULL DEFAULT 0,
    `used_days` DECIMAL(5,2) NOT NULL DEFAULT 0,
    `carried_over` DECIMAL(5,2) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`balance_id`),
    UNIQUE KEY `idx_person_type_year` (`person_id`, `leave_type_id`, `year`),
    KEY `idx_person` (`person_id`),
    KEY `idx_type` (`leave_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `0_hrm_leave_requests` (
    `request_id` INT(11) NOT NULL AUTO_INCREMENT,
    `person_id` INT(11) NOT NULL,
    `leave_type_id` INT(11) NOT NULL,
    `from_date` DATE NOT NULL,
    `to_date` DATE NOT NULL,
    `days` DECIMAL(5,2) NOT NULL,
    `reason` VARCHAR(255) NULL,
    `state` VARCHAR(24) NOT NULL DEFAULT 'draft',
    `current_approver_id` INT(11) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`request_id`),
    KEY `idx_person_state` (`person_id`, `state`),
    KEY `idx_from_to` (`from_date`, `to_date`),
    KEY `idx_current_approver` (`current_approver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `0_hrm_leave_request_days` (
    `day_id` INT(11) NOT NULL AUTO_INCREMENT,
    `request_id` INT(11) NOT NULL,
    `date` DATE NOT NULL,
    `consumed` DECIMAL(5,2) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`day_id`),
    UNIQUE KEY `idx_req_date` (`request_id`, `date`),
    KEY `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;