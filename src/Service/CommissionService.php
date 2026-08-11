<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Service;

use ksfraser\FrontAccounting\HRM\Repository\CommissionRepository;

/**
 * Sales commission calculation on imported FA orders.
 *
 * Consumes the order_imported event broadcast by source modules
 * (Square, WooCommerce). For each employee assigned to the order's
 * customer, the active commission rate is applied and a pending
 * commission entry is recorded.
 *
 * @BABOK Related: FR-HRM-007
 * @since 1.0.0
 */
class CommissionService
{
    private CommissionRepository $repo;

    public function __construct()
    {
        $this->repo = new CommissionRepository();
    }

    /**
     * Compute a commission amount from an order total and a rate.
     *
     * Percent rates are applied against the order total; fixed rates are
     * returned as-is. Results are rounded to two decimals.
     *
     * @param float $orderTotal Order total
     * @param array $rate Rate data (rate_type, rate)
     * @return float Commission amount
     */
    public function computeCommission(float $orderTotal, array $rate): float
    {
        $rateType = (string)($rate['rate_type'] ?? 'percent');
        $rateValue = (float)($rate['rate'] ?? 0);

        if ($rateType === 'fixed') {
            $amount = $rateValue;
        } else {
            $amount = $orderTotal * $rateValue / 100;
        }

        return round($amount, 2);
    }

    /**
     * Handle an order_imported event payload.
     *
     * Creates pending commission entries for every active employee-customer
     * assignment matching the payload's customer. Skips employees that
     * already have an entry for the order (dedup by fa_order_no/person).
     *
     * @param array $payload order_imported payload
     * @return int[] Created entry ids
     */
    public function onOrderImported(array $payload): array
    {
        $customerId = (int)($payload['customer_id'] ?? 0);
        if ($customerId <= 0) {
            return [];
        }

        $orderNo = (int)($payload['fa_order_no'] ?? 0);
        $transType = (int)($payload['fa_trans_type'] ?? 10);
        $source = (string)($payload['source'] ?? 'all');
        $orderTotal = (float)($payload['order_total'] ?? 0);
        $orderDate = (string)($payload['order_date'] ?? date('Y-m-d'));

        $existingPersonIds = array_map(
            fn($entry) => $entry->getPersonId(),
            $this->repo->findEntriesByOrder($orderNo, $transType)
        );

        $assignments = $this->repo->findAssignmentsByCustomer($customerId, $source);

        $created = [];
        foreach ($assignments as $assignment) {
            $personId = $assignment->getPersonId();
            if (in_array($personId, $existingPersonIds, true)) {
                continue;
            }

            $rate = $this->repo->findActiveRateByPerson($personId, $orderDate, $source);
            if ($rate === null) {
                continue;
            }

            $entryId = $this->repo->createEntry([
                'person_id' => $personId,
                'fa_order_no' => $orderNo,
                'fa_trans_type' => $transType,
                'source' => $source,
                'source_order_id' => (string)($payload['source_order_id'] ?? ''),
                'customer_id' => $customerId,
                'order_total' => $orderTotal,
                'commission_amount' => $this->computeCommission($orderTotal, $rate->toArray()),
                'rate' => $rate->getRate(),
                'status' => 'pending',
                'order_date' => $orderDate,
            ]);

            $created[] = $entryId;
            $existingPersonIds[] = $personId;
        }

        return $created;
    }

    /**
     * Get the active commission rate for an employee on a date.
     *
     * @param int $personId Employee person_id
     * @param string $asOf Date (Y-m-d)
     * @param string $source Sales source filter
     * @return array|null Rate array or null
     */
    public function getActiveRateForPerson(int $personId, string $asOf, string $source = 'all'): ?array
    {
        $rate = $this->repo->findActiveRateByPerson($personId, $asOf, $source);
        return $rate ? $rate->toArray() : null;
    }

    /**
     * List commission rates.
     *
     * @return array
     */
    public function listRates(): array
    {
        return $this->repo->listRates();
    }

    /**
     * Save a commission rate.
     *
     * @param array $data Rate fields
     * @return int New rate_id
     */
    public function saveRate(array $data): int
    {
        return $this->repo->saveRate($data);
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
        $this->repo->updateRate($id, $data);
    }

    /**
     * Delete a commission rate.
     *
     * @param int $id rate_id
     * @return void
     */
    public function deleteRate(int $id): void
    {
        $this->repo->deleteRate($id);
    }

    /**
     * List employee-customer assignments.
     *
     * @return array
     */
    public function listAssignments(): array
    {
        return $this->repo->listAssignments();
    }

    /**
     * Save an employee-customer assignment.
     *
     * @param array $data Assignment fields
     * @return int New id
     */
    public function saveAssignment(array $data): int
    {
        return $this->repo->saveAssignment($data);
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
        $this->repo->updateAssignment($id, $data);
    }

    /**
     * Delete an assignment.
     *
     * @param int $id Assignment id
     * @return void
     */
    public function deleteAssignment(int $id): void
    {
        $this->repo->deleteAssignment($id);
    }

    /**
     * List commission entries.
     *
     * @return array
     */
    public function listEntries(): array
    {
        return $this->repo->listEntries();
    }

    /**
     * List commission entries for one employee.
     *
     * @param int $personId Employee person_id
     * @return array
     */
    public function listEntriesByPerson(int $personId): array
    {
        return $this->repo->listEntriesByPerson($personId);
    }

    /**
     * List commission entries with a given status.
     *
     * @param string $status Entry status
     * @return array
     */
    public function listEntriesByStatus(string $status): array
    {
        return $this->repo->listEntriesByStatus($status);
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
        $this->repo->updateEntryStatus($entryId, $status);
    }
}
