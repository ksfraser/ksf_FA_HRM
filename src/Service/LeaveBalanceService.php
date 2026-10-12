<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Service;

use ksfraser\FrontAccounting\HRM\Repository\LeaveBalanceRepository;
use ksfraser\FrontAccounting\HRM\Repository\LookupRepository;

/**
 * LeaveBalanceService — read/allocate logic behind pages/leave.php.
 *
 * The page owns no SQL and no validation: it collects the filter, hands it to
 * search(), and hands a POST body to allocate(). Everything that touches the
 * leave balance table lives here or in LeaveBalanceRepository.
 *
 * Balance accounting convention (matches LeaveBalanceRepository::balanceRemaining):
 * remaining = allocated_days + carried_over - used_days.
 *
 * PHP 7.3 compatible.
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §10 (page -> service -> repository)
 * @BABOK Related: FR-HRM-001-005 (leave balances)
 */
class LeaveBalanceService
{
    /** @var LeaveBalanceRepository */
    private $repo;

    /** @var LookupRepository */
    private $lookups;

    /**
     * @param LeaveBalanceRepository|null $repo    DI seam for tests
     * @param LookupRepository|null       $lookups DI seam for tests
     *
     * @since 1.0.0
     */
    public function __construct(?LeaveBalanceRepository $repo = null, ?LookupRepository $lookups = null)
    {
        $this->repo = $repo ?? new LeaveBalanceRepository();
        $this->lookups = $lookups ?? new LookupRepository();
    }

    /**
     * Run a filter search over the leave balances.
     *
     * @param array $filters person_id / leave_type_id / year; '' or 0 = all
     *
     * @return array list of balance rows
     *
     * @since 1.0.0
     */
    public function search(array $filters = []): array
    {
        return $this->repo->listBalances($this->normaliseFilters($filters));
    }

    /**
     * Load one balance row for edit, or null when it does not exist.
     *
     * @param int $balanceId balance_id
     *
     * @return array|null
     *
     * @since 1.0.0
     */
    public function find(int $balanceId): ?array
    {
        foreach ($this->repo->listBalances() as $row) {
            if ((int) $row['balance_id'] === $balanceId) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Allocate (create or adjust) a leave balance.
     *
     * Creating is idempotent on (person, type, year): when the row already
     * exists the days given are treated as an adjustment to the stored
     * allocation rather than a second row.
     *
     * @param array $data person_id, leave_type_id, year, allocated_days,
     *                    carried_over
     *
     * @return array ['ok'=>bool, 'balance_id'=>int, 'error'=>?string]
     *
     * @since 1.0.0
     */
    public function allocate(array $data): array
    {
        $personId = (int) ($data['person_id'] ?? 0);
        $typeId = (int) ($data['leave_type_id'] ?? 0);
        $year = (int) ($data['year'] ?? 0);

        if ($personId <= 0) {
            return ['ok' => false, 'balance_id' => 0, 'error' => 'Employee is required.'];
        }
        if ($typeId <= 0) {
            return ['ok' => false, 'balance_id' => 0, 'error' => 'Leave type is required.'];
        }
        if ($year < 2000 || $year > 2100) {
            return ['ok' => false, 'balance_id' => 0, 'error' => 'A four digit leave year is required.'];
        }

        $allocated = (float) ($data['allocated_days'] ?? 0);
        $carried = (float) ($data['carried_over'] ?? 0);
        if ($allocated < 0 || $carried < 0) {
            return ['ok' => false, 'balance_id' => 0, 'error' => 'Days cannot be negative.'];
        }

        $existing = $this->repo->findBalance($personId, $typeId, $year);
        if ($existing === null) {
            return [
                'ok' => true,
                'balance_id' => $this->repo->upsertBalance($personId, $typeId, $year, $allocated, $carried),
                'error' => null,
            ];
        }

        $this->repo->updateAllocation(
            (int) $existing['balance_id'],
            (float) $existing['allocated_days'] + $allocated,
            (float) $existing['carried_over'] + $carried
        );

        return ['ok' => true, 'balance_id' => (int) $existing['balance_id'], 'error' => null];
    }

    /**
     * Release a balance row entirely.
     *
     * Refused when leave has already been taken against the row: the day book
     * (0_hrm_leave_request_days) is the append-only record of what was
     * granted, so removing the allocation would orphan the audit trail. Adjust
     * allocated_days through allocate() for historical years instead.
     *
     * @param int $balanceId balance_id
     *
     * @return array ['ok'=>bool, 'error'=>?string]
     *
     * @since 1.0.0
     */
    public function release(int $balanceId): array
    {
        if ($balanceId <= 0) {
            return ['ok' => false, 'error' => 'Invalid leave balance.'];
        }
        $row = $this->repo->findById($balanceId);
        if ($row === null) {
            return ['ok' => false, 'error' => 'That leave balance no longer exists.'];
        }
        if ((float) $row['used_days'] > 0) {
            return ['ok' => false, 'error' => 'This balance cannot be released: leave has '
                . 'already been taken against it. Adjust the allocation instead.'];
        }
        $this->repo->deleteBalance($balanceId);
        return ['ok' => true, 'error' => null];
    }

    /**
     * Employee options for the filter / form selectors.
     *
     * @return array<int,string>
     *
     * @since 1.0.0
     */
    public function personOptions(): array
    {
        return $this->lookups->personOptions();
    }

    /**
     * Leave type options for the filter / form selectors.
     *
     * @return array<int,string>
     *
     * @since 1.0.0
     */
    public function leaveTypeOptions(): array
    {
        return $this->repo->distinctLeaveTypes();
    }

    /**
     * Coerce raw request input into typed filter values.
     *
     * @param array $filters
     *
     * @return array
     *
     * @since 1.0.0
     */
    protected function normaliseFilters(array $filters): array
    {
        return [
            'person_id' => (int) ($filters['person_id'] ?? 0),
            'leave_type_id' => (int) ($filters['leave_type_id'] ?? 0),
            'year' => (int) ($filters['year'] ?? 0),
        ];
    }
}