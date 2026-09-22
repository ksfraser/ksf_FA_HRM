-- ============================================================================
-- ksf_FA_HRM — Worked-Window View (FR-HRM-007-001 REQ-4 / UC-HRM-007-001)
-- ============================================================================
-- Append-only, read-only evidence rows that pay runs may consult. HRM records
-- ONE row per (closed event, active employee) on an HR-track event close.
-- Rows are INSERT-ed once (idempotent on re-close), never updated, never
-- deleted, and never mutate the EventClosedDto payload.
--
-- Cross-module contract (ARCH-007, FR-CAL-007-003): HRM subscribes to
-- ksf_event_closed but contributes ONLY to its own table; the EventClosedDto
-- stays untouched. The UNIQUE(event_id, person_id) key makes re-close a
-- no-op so this really is append-only worked-window evidence.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `0_hrm_event_windows` (
    `window_id`   INT(11) NOT NULL AUTO_INCREMENT,
    `event_id`    INT(11) NOT NULL COMMENT 'FK 0_cal_entries.id (closed event)',
    `person_id`   INT(11) NOT NULL COMMENT 'FK 0_crm_persons.id (active employee)',
    `started_at`  DATETIME NOT NULL COMMENT 'Event started_at mirror (never re-derived)',
    `closed_at`   DATETIME NOT NULL COMMENT 'Event closed_at mirror (never re-derived)',
    `track_token` VARCHAR(32) NOT NULL DEFAULT 'training'
        COMMENT 'HR track token that triggered recording',
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`window_id`),
    UNIQUE KEY `uq_event_person` (`event_id`, `person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
