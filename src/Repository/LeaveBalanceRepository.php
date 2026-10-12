<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Repository;

/**
 * Leave balance persistence (BR-HRM-01 FR-HRM-001-005).
 *
 * One balance row per (person, leave_type, year): allocated + carried_over
 * minus used_days is the submit/approve guard's balance_remaining. used_days
 * is incremented by consume_days on approval of a request; nothing else ever
 * writes it (append-only discipline, mirrors BR-007/BR-HRM-01 day book).
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 */
class LeaveBalanceRepository
{
    use FatRepositoryTrait;

    public function findBalance(int $personId, int $leaveTypeId, int $year): ?array
    {
        $sql = "SELECT * FROM " . TB_PREF . "hrm_leave_balances
            WHERE person_id = " . $this->intVal($personId) .
            " AND leave_type_id = " . $this->intVal($leaveTypeId) .
            " AND `year` = " . $this->intVal($year);
        return $this->dbFetchAssoc($this->dbQuery($sql));
    }

    /**
     * balance_remaining = allocated + carried_over - used_days.
     * Returns 0.0 when no balance row exists (nothing allocated yet).
     */
    public function balanceRemaining(int $personId, int $leaveTypeId, int $year): float
    {
        $row = $this->findBalance($personId, $leaveTypeId, $year);
        if ($row === null) {
            return 0.0;
        }
        return (float)$row['allocated_days'] + (float)$row['carried_over'] - (float)$row['used_days'];
    }

    /** consume_days: decrement the remaining balance for (person, type, year). */
    public function incrementUsed(int $personId, int $leaveTypeId, int $year, float $days): void
    {
        $row = $this->findBalance($personId, $leaveTypeId, $year);
        if ($row === null) {
            return;
        }
        $sql = "UPDATE " . TB_PREF . "hrm_leave_balances SET
            used_days = " . $this->floatVal((float)$row['used_days'] + $days) . ",
            updated_at = " . $this->escape(date('Y-m-d H:i:s')) . "
            WHERE balance_id = " . $this->intVal($row['balance_id']);
        $this->dbQuery($sql);
    }

    /**
     * Read-side list joined to persons + leave type names (balance page).
     *
     * The leave-type master (0_leave_types) is documented as ksf_FA_Leave's
     * master and is NOT part of the ksf_FA_HRM install, so it is joined only
     * when it actually exists. Otherwise the type falls back to its numeric
     * id, which keeps the balance page rendering on a standalone HRM install
     * instead of erroring on every load.
     *
     * @param array $filters ['person_id'=>int,'leave_type_id'=>int,'year'=>int]
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function listBalances(array $filters = []): array
    {
        $hasTypes = $this->tableExists('leave_types');
        $typeExpr = $hasTypes
            ? "lt.type_name"
            : "CAST(b.leave_type_id AS CHAR)";

        $sql = "SELECT b.*, c.name AS employee_name,
                COALESCE(" . $typeExpr . ", CONCAT('Type ', b.leave_type_id)) AS leave_type_name,
                (b.allocated_days + b.carried_over - b.used_days) AS balance
            FROM " . TB_PREF . "hrm_leave_balances b
            LEFT JOIN " . TB_PREF . "crm_persons c ON b.person_id = c.id"
            . ($hasTypes
                ? " LEFT JOIN " . TB_PREF . "leave_types lt ON b.leave_type_id = lt.leave_type_id"
                : "")
            . $this->balanceFilters($filters)
            . " ORDER BY c.name ASC, b.`year` DESC";

        return $this->dbFetchAll($this->dbQuery($sql));
    }

    /**
     * Build the optional WHERE fragment for listBalances().
     *
     * @param array $filters person_id / leave_type_id / year; '' or 0 = no filter
     *
     * @return string
     *
     * @since 1.0.0
     */
    protected function balanceFilters(array $filters): string
    {
        $where = '';
        if (!empty($filters['person_id'])) {
            $where .= " AND b.person_id = " . $this->intVal($filters['person_id']);
        }
        if (!empty($filters['leave_type_id'])) {
            $where .= " AND b.leave_type_id = " . $this->intVal($filters['leave_type_id']);
        }
        if (!empty($filters['year'])) {
            $where .= " AND b.`year` = " . $this->intVal($filters['year']);
        }
        return $where === '' ? '' : " WHERE 1 = 1" . $where;
    }

    /**
     * The leave types that actually appear on a balance row.
     *
     * Used as the filter dropdown on the leave page. Unlike the catalogue
     * read in LookupRepository this never depends on 0_leave_types existing,
     * so the filter is always populated, even on a standalone HRM install.
     *
     * @return array<int,string> id => label
     *
     * @since 1.0.0
     */
    public function distinctLeaveTypes(): array
    {
        $hasTypes = $this->tableExists('leave_types');
        $sql = "SELECT DISTINCT b.leave_type_id"
            . ($hasTypes ? ", lt.type_name" : "")
            . " FROM " . TB_PREF . "hrm_leave_balances b"
            . ($hasTypes ? " LEFT JOIN " . TB_PREF . "leave_types lt ON b.leave_type_id = lt.leave_type_id" : "")
            . " ORDER BY b.leave_type_id";

        $out = [];
        foreach ($this->dbFetchAll($this->dbQuery($sql)) as $row) {
            $id = (int) $row['leave_type_id'];
            $out[$id] = !empty($row['type_name']) ? (string) $row['type_name'] : 'Type ' . $id;
        }
        return $out;
    }

    /** Insert a fresh balance row (seed / BR-COM-04 accrual). Idempotent by key. */
    public function upsertBalance(int $personId, int $leaveTypeId, int $year, float $allocated, float $carriedOver = 0.0): int
    {
        $row = $this->findBalance($personId, $leaveTypeId, $year);
        if ($row !== null) {
            return (int)$row['balance_id'];
        }
        $sql = "INSERT INTO " . TB_PREF . "hrm_leave_balances
            (person_id, leave_type_id, `year`, allocated_days, used_days, carried_over, created_at, updated_at)
            VALUES (" . $this->intVal($personId) . ", " . $this->intVal($leaveTypeId) . ", " .
            $this->intVal($year) . ", " . $this->floatVal($allocated) . ", 0, " .
            $this->floatVal($carriedOver) . ", " . $this->escape(date('Y-m-d H:i:s')) . ", " .
            $this->escape(date('Y-m-d H:i:s')) . ")";
        $this->dbQuery($sql);
        return $this->dbInsertId();
    }

    /**
     * Overwrite the allocated / carried-over figures on an existing balance.
     *
     * used_days is deliberately NOT touched here: it is the append-only
     * consumption counter maintained by incrementUsed() on approval.
     *
     * @param int   $balanceId  balance_id
     * @param float $allocated  new allocated_days
     * @param float $carriedOver new carried_over
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function updateAllocation(int $balanceId, float $allocated, float $carriedOver): void
    {
        $sql = "UPDATE " . TB_PREF . "hrm_leave_balances SET
            allocated_days = " . $this->floatVal($allocated) . ",
            carried_over = " . $this->floatVal($carriedOver) . ",
            updated_at = " . $this->escape(date('Y-m-d H:i:s')) . "
            WHERE balance_id = " . $this->intVal($balanceId);
        $this->dbQuery($sql);
    }

    /**
     * Fetch one balance row by its primary key.
     *
     * @param int $balanceId balance_id
     *
     * @return array|null
     *
     * @since 1.0.0
     */
    public function findById(int $balanceId): ?array
    {
        $sql = "SELECT * FROM " . TB_PREF . "hrm_leave_balances
            WHERE balance_id = " . $this->intVal($balanceId);
        return $this->dbFetchAssoc($this->dbQuery($sql));
    }

    /**
     * Remove a balance row outright.
     *
     * Refused once anything has been taken against the row: the append-only
     * day book (0_hrm_leave_request_days) references approved leave, and
     * deleting the allocation would orphan that audit trail. Adjust
     * allocated_days through updateAllocation() instead.
     *
     * FA 2.4.3 exposes no db_affected_rows(), so the guard is an explicit
     * read rather than a post-delete row count.
     *
     * @param int $balanceId balance_id
     *
     * @return bool true when a row was actually removed
     *
     * @since 1.0.0
     */
    public function deleteBalance(int $balanceId): bool
    {
        $row = $this->findById($balanceId);
        if ($row === null || (float) $row['used_days'] > 0) {
            return false;
        }
        $sql = "DELETE FROM " . TB_PREF . "hrm_leave_balances
            WHERE balance_id = " . $this->intVal($balanceId);
        $this->dbQuery($sql);
        return true;
    }
}