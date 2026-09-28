<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Repository;

/**
 * Leave request persistence (BR-HRM-01 FR-HRM-001-001).
 *
 * Thin FA db_* access over the 0_hrm_leave_requests and
 * 0_hrm_leave_request_days tables. Request rows carry the BR-COM-02-facing
 * `state` column (draft/submitted/pending/approved/...), mirrored by the
 * workflow state store; current_approver_id is the active chain level.
 * The day book is append-only: each calendar day of a request is INSERT-ed
 * once on approval (consume_days), never updated or deleted.
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 */
class LeaveRequestRepository
{
    use FatRepositoryTrait;

    public function insertRequest(array $data): int
    {
        $from = $this->escape($data['from_date']);
        $to   = $this->escape($data['to_date']);
        $sql = "INSERT INTO " . TB_PREF . "hrm_leave_requests
            (person_id, leave_type_id, from_date, to_date, days, reason, state, current_approver_id, created_at, updated_at)
            VALUES (" .
            $this->intVal($data['person_id']) . ", " .
            $this->intVal($data['leave_type_id']) . ", '$from', '$to', " .
            $this->floatVal($data['days']) . ", " .
            $this->escape($data['reason'] ?? '') . ", " .
            $this->escape($data['state'] ?? 'draft') . ", " .
            ($data['current_approver_id'] ?? 'NULL') . ", " .
            $this->escape(date('Y-m-d H:i:s')) . ", " .
            $this->escape(date('Y-m-d H:i:s')) . ")";
        $this->dbQuery($sql);
        return $this->dbInsertId();
    }

    public function findByRequestId(int $requestId): ?array
    {
        $sql = "SELECT r.*, c.name AS requester_name
            FROM " . TB_PREF . "hrm_leave_requests r
            LEFT JOIN " . TB_PREF . "crm_persons c ON r.person_id = c.id
            WHERE r.request_id = " . $this->intVal($requestId);
        return $this->dbFetchAssoc($this->dbQuery($sql));
    }

    public function updateState(int $requestId, string $state, ?int $currentApproverId = null, ?string $days = null): void
    {
        $sets = ["state = '" . $this->escape($state) . "'"];
        if ($currentApproverId !== null) {
            $sets[] = "current_approver_id = " . $this->intVal($currentApproverId);
        }
        if ($days !== null) {
            $sets[] = "days = " . $this->floatVal($days);
        }
        $sets[] = "updated_at = '" . $this->escape(date('Y-m-d H:i:s')) . "'";
        $sql = "UPDATE " . TB_PREF . "hrm_leave_requests SET " . implode(', ', $sets) .
            " WHERE request_id = " . $this->intVal($requestId);
        $this->dbQuery($sql);
    }

    public function listForPerson(int $personId, string $state = ''): array
    {
        $sql = "SELECT r.*, c.name AS requester_name,
                (SELECT COUNT(d.day_id) FROM " . TB_PREF . "hrm_leave_request_days d
                 WHERE d.request_id = r.request_id) AS days_booked
            FROM " . TB_PREF . "hrm_leave_requests r
            LEFT JOIN " . TB_PREF . "crm_persons c ON r.person_id = c.id
            WHERE r.person_id = " . $this->intVal($personId);
        if ($state !== '') {
            $sql .= " AND r.state = '" . $this->escape($state) . "'";
        }
        $sql .= " ORDER BY r.from_date DESC";
        return $this->dbFetchAll($this->dbQuery($sql));
    }

    /** Requests currently routed to a given approver (their inbox). */
    public function listPendingForApprover(int $personId): array
    {
        $sql = "SELECT r.*, c.name AS requester_name
            FROM " . TB_PREF . "hrm_leave_requests r
            LEFT JOIN " . TB_PREF . "crm_persons c ON r.person_id = c.id
            WHERE r.state = 'pending' AND r.current_approver_id = " . $this->intVal($personId) .
            " ORDER BY r.from_date ASC";
        return $this->dbFetchAll($this->dbQuery($sql));
    }

    /** Approved/scheduled requests overlapping a date range — calendar feed. */
    public function listApprovedInRange(string $fromDate, string $toDate): array
    {
        $sql = "SELECT r.*, c.name AS requester_name, c.id AS requester_person_id
            FROM " . TB_PREF . "hrm_leave_requests r
            LEFT JOIN " . TB_PREF . "crm_persons c ON r.person_id = c.id
            WHERE r.state IN ('approved','scheduled')
              AND r.from_date <= '" . $this->escape($toDate) . "'
              AND r.to_date >= '" . $this->escape($fromDate) . "'
            ORDER BY r.from_date ASC";
        return $this->dbFetchAll($this->dbQuery($sql));
    }

    /** Chronologically first concurrent approved request that could block a new request. */
    public function findBlocker(int $personId, int $leaveTypeId, string $year, int $excludeRequestId): ?array
    {
        $sql = "SELECT r.*, c.name AS requester_name
            FROM " . TB_PREF . "hrm_leave_requests r
            LEFT JOIN " . TB_PREF . "crm_persons c ON r.person_id = c.id
            WHERE r.person_id = " . $this->intVal($personId) .
            " AND r.leave_type_id = " . $this->intVal($leaveTypeId) .
            " AND r.state IN ('approved','scheduled','pending')
              AND YEAR(r.from_date) = " . $this->intVal($year) .
            " AND r.request_id <> " . $this->intVal($excludeRequestId) .
            " ORDER BY r.from_date ASC LIMIT 1";
        return $this->dbFetchAssoc($this->dbQuery($sql));
    }

    /** Single calendar day for the append-only day book. */
    public function insertDay(int $requestId, string $date, float $consumed): void
    {
        $sql = "INSERT INTO " . TB_PREF . "hrm_leave_request_days
            (request_id, date, consumed, created_at)
            VALUES (" . $this->intVal($requestId) . ", '" . $this->escape($date) . "', " .
            $this->floatVal($consumed) . ", '" . $this->escape(date('Y-m-d H:i:s')) . "')";
        $this->dbQuery($sql);
    }

    /** Every calendar date of a request (for consume_days). */
    public function requestDayRange(int $requestId): array
    {
        $sql = "SELECT `date` FROM " . TB_PREF . "hrm_leave_request_days
            WHERE request_id = " . $this->intVal($requestId) . " ORDER BY `date` ASC";
        return $this->dbFetchAll($this->dbQuery($sql));
    }
}