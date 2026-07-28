<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Service;

use ksfraser\FrontAccounting\HRM\Repository\PayrollRepository;

class PayrollService
{
    private PayrollRepository $payrollRepo;

    public function __construct()
    {
        $this->payrollRepo = new PayrollRepository();
    }

    public function listAll(): array
    {
        return $this->payrollRepo->findAll();
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
