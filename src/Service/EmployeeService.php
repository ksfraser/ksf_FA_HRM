<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Service;

use ksfraser\FrontAccounting\HRM\Repository\EmployeeRepository;
use ksfraser\FrontAccounting\HRM\Repository\PositionRepository;
use ksfraser\FrontAccounting\HRM\Repository\GradeRepository;
use ksfraser\FrontAccounting\HRM\Repository\LookupRepository;
use ksfraser\FrontAccounting\HRM\Exception\ValidationException;
use ksfraser\FrontAccounting\HRM\Exception\EmployeeNotFoundException;

class EmployeeService
{
    private EmployeeRepository $employeeRepo;
    private PositionRepository $positionRepo;
    private GradeRepository $gradeRepo;
    private LookupRepository $lookupRepo;

    public function __construct()
    {
        $this->employeeRepo = new EmployeeRepository();
        $this->positionRepo = new PositionRepository();
        $this->gradeRepo = new GradeRepository();
        $this->lookupRepo = new LookupRepository();
    }

    public function listAll(): array
    {
        return $this->employeeRepo->findAll();
    }

    public function listActive(): array
    {
        return $this->employeeRepo->findActive();
    }

    public function getById(int $id): array
    {
        $employee = $this->employeeRepo->findById($id);
        if (!$employee) {
            throw new EmployeeNotFoundException((string)$id);
        }
        return $employee->toArray();
    }

    public function getFormDropdowns(): array
    {
        return [
            'positions' => $this->positionRepo->findActive(),
            'grades' => $this->gradeRepo->findActive(),
            'employees' => $this->employeeRepo->findActive(),
            'employment_statuses' => $this->lookupRepo->getEmploymentStatuses(),
            'persons' => $this->lookupRepo->getCrmPersons(),
        ];
    }

    public function hire(array $data): int
    {
        $this->validateEmployeeData($data);
        return $this->employeeRepo->save($data);
    }

    public function updateEmployee(int $id, array $data): void
    {
        $this->employeeRepo->update($id, $data);
    }

    public function terminate(int $id, ?string $reason = null): void
    {
        $this->employeeRepo->update($id, [
            'is_active' => 0,
            'termination_date' => date('Y-m-d'),
        ]);
    }

    private function validateEmployeeData(array $data): void
    {
        $errors = [];
        if (empty($data['person_id'])) $errors[] = 'Contact is required';
        if (empty($data['employee_code'])) $errors[] = 'Employee code is required';
        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
