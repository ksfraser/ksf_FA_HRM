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
            updated_at = '" . $this->escape(date('Y-m-d H:i:s')) . "'
            WHERE balance_id = " . $this->intVal($row['balance_id']);
        $this->dbQuery($sql);
    }

    /** Read-side list joined to persons + leave type names (balance page). */
    public function listBalances(): array
    {
        $sql = "SELECT b.*, c.name AS employee_name,
                COALESCE(lt.type_name, CONCAT('Type ', b.leave_type_id)) AS leave_type_name,
                (b.allocated_days + b.carried_over - b.used_days) AS balance
            FROM " . TB_PREF . "hrm_leave_balances b
            LEFT JOIN " . TB_PREF . "crm_persons c ON b.person_id = c.id
            LEFT JOIN " . TB_PREF . "leave_types lt ON b.leave_type_id = lt.leave_type_id
            ORDER BY c.name ASC, b.`year` DESC";
        return $this->dbFetchAll($this->dbQuery($sql));
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
            $this->floatVal($carriedOver) . ", '" . $this->escape(date('Y-m-d H:i:s')) . "',
            '" . $this->escape(date('Y-m-d H:i:s')) . "')";
        $this->dbQuery($sql);
        return $this->dbInsertId();
    }
}