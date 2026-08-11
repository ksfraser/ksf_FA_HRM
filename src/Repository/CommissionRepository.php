<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Repository;

use ksfraser\FrontAccounting\HRM\Entity\CommissionRate;
use ksfraser\FrontAccounting\HRM\Entity\CommissionEntry;
use ksfraser\FrontAccounting\HRM\Entity\CommissionAssignment;

/**
 * Data access for sales commission rates, assignments, and entries.
 *
 * @BABOK Related: FR-HRM-004/005/006
 * @since 1.0.0
 */
class CommissionRepository
{
    use FatRepositoryTrait;

    private const RATES_TABLE = 'hrm_commission_rates';
    private const ASSIGNMENTS_TABLE = 'hrm_commission_assignments';
    private const ENTRIES_TABLE = 'hrm_commission_entries';

    /**
     * Insert a commission rate.
     *
     * @param array $data Rate fields
     * @return int New rate_id
     */
    public function saveRate(array $data): int
    {
        $sql = "INSERT INTO " . TB_PREF . self::RATES_TABLE . "
            (person_id, source, rate_type, rate, effective_from, effective_to, is_active)
            VALUES (" .
            $this->intVal($data['person_id']) . ", " .
            $this->escape($data['source'] ?? 'all') . ", " .
            $this->escape($data['rate_type'] ?? 'percent') . ", " .
            $this->floatVal($data['rate'] ?? 0) . ", " .
            $this->escape($data['effective_from']) . ", " .
            (isset($data['effective_to']) && $data['effective_to'] !== null && $data['effective_to'] !== '' ? $this->escape($data['effective_to']) : 'NULL') . ", " .
            ($data['is_active'] ?? 1 ? 1 : 0) . ")";
        $this->dbQuery($sql);
        return $this->dbInsertId();
    }

    /**
     * Update a commission rate.
     *
     * @param int $id rate_id
     * @param array $data Fields to update
     * @return void
     */
    public function updateRate(int $id, array $data): void
    {
        $sets = [];
        if (isset($data['source'])) $sets[] = "`source` = " . $this->escape($data['source']);
        if (isset($data['rate_type'])) $sets[] = "`rate_type` = " . $this->escape($data['rate_type']);
        if (isset($data['rate'])) $sets[] = "`rate` = " . $this->floatVal($data['rate']);
        if (isset($data['effective_from'])) $sets[] = "`effective_from` = " . $this->escape($data['effective_from']);
        if (array_key_exists('effective_to', $data)) {
            $sets[] = "`effective_to` = " . ($data['effective_to'] !== null && $data['effective_to'] !== '' ? $this->escape($data['effective_to']) : 'NULL');
        }
        if (isset($data['is_active'])) $sets[] = "`is_active` = " . ($data['is_active'] ? 1 : 0);
        if (empty($sets)) {
            return;
        }
        $this->dbQuery("UPDATE " . TB_PREF . self::RATES_TABLE . " SET " . implode(', ', $sets) .
            " WHERE rate_id = " . $this->intVal($id));
    }

    /**
     * Delete a commission rate.
     *
     * @param int $id rate_id
     * @return void
     */
    public function deleteRate(int $id): void
    {
        $this->dbQuery("DELETE FROM " . TB_PREF . self::RATES_TABLE . " WHERE rate_id = " . $this->intVal($id));
    }

    /**
     * Find a rate by id.
     *
     * @param int $id rate_id
     * @return CommissionRate|null
     */
    public function findRateById(int $id): ?CommissionRate
    {
        $sql = "SELECT * FROM " . TB_PREF . self::RATES_TABLE .
            " WHERE rate_id = " . $this->intVal($id);
        $row = $this->dbFetchAssoc($this->dbQuery($sql));
        return $row ? new CommissionRate($row) : null;
    }

    /**
     * Find the active rate for an employee effective on a given date.
     *
     * @param int $personId Employee person_id
     * @param string $asOf Date (Y-m-d)
     * @param string $source Sales source filter
     * @return CommissionRate|null
     */
    public function findActiveRateByPerson(int $personId, string $asOf, string $source = 'all'): ?CommissionRate
    {
        $sql = "SELECT * FROM " . TB_PREF . self::RATES_TABLE . "
            WHERE person_id = " . $this->intVal($personId) . "
              AND is_active = 1
              AND effective_from <= " . $this->escape($asOf) . "
              AND (effective_to IS NULL OR effective_to >= " . $this->escape($asOf) . ")
              AND (source = 'all' OR source = " . $this->escape($source) . ")
            ORDER BY effective_from DESC, rate_id DESC
            LIMIT 1";
        $row = $this->dbFetchAssoc($this->dbQuery($sql));
        return $row ? new CommissionRate($row) : null;
    }

    /**
     * List all commission rates.
     *
     * @return CommissionRate[]
     */
    public function listRates(): array
    {
        $sql = "SELECT * FROM " . TB_PREF . self::RATES_TABLE . " ORDER BY person_id, effective_from DESC";
        return array_map(fn($r) => new CommissionRate($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    /**
     * List commission rates for one employee.
     *
     * @param int $personId Employee person_id
     * @return CommissionRate[]
     */
    public function listRatesByPerson(int $personId): array
    {
        $sql = "SELECT * FROM " . TB_PREF . self::RATES_TABLE .
            " WHERE person_id = " . $this->intVal($personId) . " ORDER BY effective_from DESC";
        return array_map(fn($r) => new CommissionRate($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    /**
     * Insert an employee-customer assignment.
     *
     * @param array $data Assignment fields
     * @return int New id
     */
    public function saveAssignment(array $data): int
    {
        $sql = "INSERT INTO " . TB_PREF . self::ASSIGNMENTS_TABLE . "
            (person_id, customer_id, source, is_active)
            VALUES (" .
            $this->intVal($data['person_id']) . ", " .
            $this->intVal($data['customer_id']) . ", " .
            $this->escape($data['source'] ?? 'all') . ", " .
            ($data['is_active'] ?? 1 ? 1 : 0) . ")";
        $this->dbQuery($sql);
        return $this->dbInsertId();
    }

    /**
     * Update an assignment.
     *
     * @param int $id Assignment id
     * @param array $data Fields to update
     * @return void
     */
    public function updateAssignment(int $id, array $data): void
    {
        $sets = [];
        if (isset($data['source'])) $sets[] = "`source` = " . $this->escape($data['source']);
        if (isset($data['is_active'])) $sets[] = "`is_active` = " . ($data['is_active'] ? 1 : 0);
        if (empty($sets)) {
            return;
        }
        $this->dbQuery("UPDATE " . TB_PREF . self::ASSIGNMENTS_TABLE . " SET " . implode(', ', $sets) .
            " WHERE id = " . $this->intVal($id));
    }

    /**
     * Delete an assignment.
     *
     * @param int $id Assignment id
     * @return void
     */
    public function deleteAssignment(int $id): void
    {
        $this->dbQuery("DELETE FROM " . TB_PREF . self::ASSIGNMENTS_TABLE . " WHERE id = " . $this->intVal($id));
    }

    /**
     * Find active assignments for a customer.
     *
     * @param int $customerId debtors_master.debtor_no
     * @param string $source Sales source filter
     * @return CommissionAssignment[]
     */
    public function findAssignmentsByCustomer(int $customerId, string $source = 'all'): array
    {
        $sql = "SELECT * FROM " . TB_PREF . self::ASSIGNMENTS_TABLE . "
            WHERE customer_id = " . $this->intVal($customerId) . "
              AND is_active = 1
              AND (source = 'all' OR source = " . $this->escape($source) . ")
            ORDER BY id ASC";
        return array_map(fn($r) => new CommissionAssignment($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    /**
     * List all assignments.
     *
     * @return CommissionAssignment[]
     */
    public function listAssignments(): array
    {
        $sql = "SELECT * FROM " . TB_PREF . self::ASSIGNMENTS_TABLE . " ORDER BY customer_id, person_id";
        return array_map(fn($r) => new CommissionAssignment($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    /**
     * Insert a commission entry.
     *
     * @param array $data Entry fields
     * @return int New entry_id
     */
    public function createEntry(array $data): int
    {
        $sql = "INSERT INTO " . TB_PREF . self::ENTRIES_TABLE . "
            (person_id, fa_order_no, fa_trans_type, source, source_order_id, customer_id,
             order_total, commission_amount, rate, status, order_date)
            VALUES (" .
            $this->intVal($data['person_id']) . ", " .
            $this->intVal($data['fa_order_no'] ?? 0) . ", " .
            $this->intVal($data['fa_trans_type'] ?? 10) . ", " .
            $this->escape($data['source'] ?? 'all') . ", " .
            (isset($data['source_order_id']) && $data['source_order_id'] !== null && $data['source_order_id'] !== '' ? $this->escape($data['source_order_id']) : 'NULL') . ", " .
            (isset($data['customer_id']) && $data['customer_id'] !== null ? $this->intVal($data['customer_id']) : 'NULL') . ", " .
            $this->floatVal($data['order_total'] ?? 0) . ", " .
            $this->floatVal($data['commission_amount'] ?? 0) . ", " .
            $this->floatVal($data['rate'] ?? 0) . ", " .
            $this->escape($data['status'] ?? 'pending') . ", " .
            (isset($data['order_date']) && $data['order_date'] !== '' ? $this->escape($data['order_date']) : 'NULL') . ")";
        $this->dbQuery($sql);
        return $this->dbInsertId();
    }

    /**
     * Find an entry by id.
     *
     * @param int $id entry_id
     * @return CommissionEntry|null
     */
    public function findEntryById(int $id): ?CommissionEntry
    {
        $sql = "SELECT * FROM " . TB_PREF . self::ENTRIES_TABLE .
            " WHERE entry_id = " . $this->intVal($id);
        $row = $this->dbFetchAssoc($this->dbQuery($sql));
        return $row ? new CommissionEntry($row) : null;
    }

    /**
     * Find entries for a given FA order.
     *
     * @param int $orderNo FA order number
     * @param int $transType FA transaction type
     * @return CommissionEntry[]
     */
    public function findEntriesByOrder(int $orderNo, int $transType = 10): array
    {
        $sql = "SELECT * FROM " . TB_PREF . self::ENTRIES_TABLE . "
            WHERE fa_order_no = " . $this->intVal($orderNo) . "
              AND fa_trans_type = " . $this->intVal($transType);
        return array_map(fn($r) => new CommissionEntry($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    /**
     * List all entries.
     *
     * @return CommissionEntry[]
     */
    public function listEntries(): array
    {
        $sql = "SELECT * FROM " . TB_PREF . self::ENTRIES_TABLE . " ORDER BY created_at DESC";
        return array_map(fn($r) => new CommissionEntry($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    /**
     * List entries for one employee.
     *
     * @param int $personId Employee person_id
     * @return CommissionEntry[]
     */
    public function listEntriesByPerson(int $personId): array
    {
        $sql = "SELECT * FROM " . TB_PREF . self::ENTRIES_TABLE .
            " WHERE person_id = " . $this->intVal($personId) . " ORDER BY created_at DESC";
        return array_map(fn($r) => new CommissionEntry($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    /**
     * List entries with a given status.
     *
     * @param string $status Entry status
     * @return CommissionEntry[]
     */
    public function listEntriesByStatus(string $status): array
    {
        $sql = "SELECT * FROM " . TB_PREF . self::ENTRIES_TABLE .
            " WHERE status = " . $this->escape($status) . " ORDER BY created_at DESC";
        return array_map(fn($r) => new CommissionEntry($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    /**
     * Update an entry's status.
     *
     * @param int $entryId entry_id
     * @param string $status New status
     * @return void
     */
    public function updateEntryStatus(int $entryId, string $status): void
    {
        $this->dbQuery("UPDATE " . TB_PREF . self::ENTRIES_TABLE .
            " SET `status` = " . $this->escape($status) .
            " WHERE entry_id = " . $this->intVal($entryId));
    }
}
