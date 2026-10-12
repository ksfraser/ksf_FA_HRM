<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Service;

use ksfraser\FrontAccounting\HRM\Repository\LookupRepository;
use ksfraser\FrontAccounting\HRM\Repository\PayrollRepository;

class PayrollService
{
    /** @var PayrollRepository */
    private $payrollRepo;

    /** @var LookupRepository|null */
    private $lookups;

    public function __construct()
    {
        $this->payrollRepo = new PayrollRepository();
    }

    public function listAll(): array
    {
        return $this->payrollRepo->findAll();
    }

    /**
     * Filtered list used by the payroll page.
     *
     * @param array $filters person_id, status, from_date, to_date
     *
     * @return Payroll[]
     *
     * @since 1.0.0
     */
    public function search(array $filters = []): array
    {
        return $this->payrollRepo->findFiltered($filters);
    }

    /**
     * Distinct payroll statuses for the filter combo.
     *
     * @return array<string,string>
     *
     * @since 1.0.0
     */
    public function statusOptions(): array
    {
        return $this->payrollRepo->distinctStatuses();
    }

    /**
     * Employee options (id => name) for the filter / form selectors.
     *
     * @return array<int,string>
     *
     * @since 1.0.0
     */
    public function personOptions(): array
    {
        if (!isset($this->lookups)) {
            $this->lookups = new LookupRepository();
        }
        return $this->lookups->personOptions();
    }

    /**
     * Create a payroll run header with validation.
     *
     * @param array $data
     *
     * @return array ['ok'=>bool, 'payroll_id'=>int, 'error'=>?string]
     *
     * @since 1.0.0
     */
    public function createRun(array $data): array
    {
        $error = $this->validate($data);
        if ($error !== null) {
            return ['ok' => false, 'payroll_id' => 0, 'error' => $error];
        }
        return ['ok' => true, 'payroll_id' => $this->payrollRepo->save($data), 'error' => null];
    }

    /**
     * Update a payroll run header with validation.
     *
     * @param int   $id
     * @param array $data
     *
     * @return array ['ok'=>bool, 'error'=>?string]
     *
     * @since 1.0.0
     */
    public function updateRun(int $id, array $data): array
    {
        $error = $this->validate($data);
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }
        $this->payrollRepo->update($id, $data);
        return ['ok' => true, 'error' => null];
    }

    /**
     * Validate a payroll header payload.
     *
     * @param array $data
     *
     * @return string|null human readable problem, or null when valid
     *
     * @since 1.0.0
     */
    protected function validate(array $data): ?string
    {
        if ((int) ($data['person_id'] ?? 0) <= 0) {
            return 'Employee is required.';
        }
        $start = trim((string) ($data['pay_period_start'] ?? ''));
        $end = trim((string) ($data['pay_period_end'] ?? ''));
        $payDate = trim((string) ($data['pay_date'] ?? ''));
        if ($start === '' || $end === '') {
            return 'Pay period start and end are required.';
        }
        if ($payDate === '') {
            return 'Pay date is required.';
        }
        foreach (['pay period start' => $start, 'pay period end' => $end,
                  'pay date' => $payDate] as $label => $value) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return 'The ' . $label . ' is not a valid date (YYYY-MM-DD).';
            }
        }
        if ($end < $start) {
            return 'Pay period end cannot be before pay period start.';
        }
        if ((float) ($data['gross_pay'] ?? 0) < 0) {
            return 'Gross pay cannot be negative.';
        }
        return null;
    }

    public function getById(int $id): array
    {
        $payroll = $this->payrollRepo->findById($id);
        return $payroll ? $payroll->toArray() : [];
    }

    public function getHistory(int $personId): array
    {
        return $this->payrollRepo->findByPerson($personId);
    }

    public function create(array $data): int
    {
        return $this->payrollRepo->save($data);
    }

    public function update(int $id, array $data): void
    {
        $this->payrollRepo->update($id, $data);
    }

    public function delete(int $id): void
    {
        $this->payrollRepo->delete($id);
    }

    public function getEntries(int $payrollId): array
    {
        return $this->payrollRepo->getEntries($payrollId);
    }

    public function addEntry(array $data): int
    {
        return $this->payrollRepo->saveEntry($data);
    }
}
