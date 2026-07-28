<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

class Employee
{
    private int $employmentId;
    private int $personId;
    private ?string $employeeCode;
    private ?int $departmentId;
    private ?int $positionId;
    private ?int $gradeId;
    private int $employmentType;
    private ?string $hireDate;
    private ?string $probationEndDate;
    private ?string $confirmationDate;
    private ?string $terminationDate;
    private ?int $separationReasonId;
    private float $salaryAmount;
    private ?string $loginId;
    private ?int $reportsToPersonId;
    private bool $isActive;
    private string $createdAt;
    private string $updatedAt;

    public function __construct(array $data)
    {
        $this->employmentId = (int)$data['employment_id'];
        $this->personId = (int)$data['person_id'];
        $this->employeeCode = $data['employee_code'] ?? null;
        $this->departmentId = $data['department_id'] ? (int)$data['department_id'] : null;
        $this->positionId = $data['position_id'] ? (int)$data['position_id'] : null;
        $this->gradeId = $data['grade_id'] ? (int)$data['grade_id'] : null;
        $this->employmentType = (int)($data['employment_type'] ?? 1);
        $this->hireDate = $data['hire_date'] ?? null;
        $this->probationEndDate = $data['probation_end_date'] ?? null;
        $this->confirmationDate = $data['confirmation_date'] ?? null;
        $this->terminationDate = $data['termination_date'] ?? null;
        $this->separationReasonId = $data['separation_reason_id'] ? (int)$data['separation_reason_id'] : null;
        $this->salaryAmount = (float)($data['salary_amount'] ?? 0);
        $this->loginId = $data['login_id'] ?? null;
        $this->reportsToPersonId = $data['reports_to_person_id'] ? (int)$data['reports_to_person_id'] : null;
        $this->isActive = (bool)($data['is_active'] ?? 1);
        $this->createdAt = $data['created_at'] ?? '';
        $this->updatedAt = $data['updated_at'] ?? '';
    }

    public function getEmploymentId(): int { return $this->employmentId; }
    public function getPersonId(): int { return $this->personId; }
    public function getEmployeeCode(): ?string { return $this->employeeCode; }
    public function getDepartmentId(): ?int { return $this->departmentId; }
    public function getPositionId(): ?int { return $this->positionId; }
    public function getGradeId(): ?int { return $this->gradeId; }
    public function getEmploymentType(): int { return $this->employmentType; }
    public function getHireDate(): ?string { return $this->hireDate; }
    public function getProbationEndDate(): ?string { return $this->probationEndDate; }
    public function getConfirmationDate(): ?string { return $this->confirmationDate; }
    public function getTerminationDate(): ?string { return $this->terminationDate; }
    public function getSeparationReasonId(): ?int { return $this->separationReasonId; }
    public function getSalaryAmount(): float { return $this->salaryAmount; }
    public function getLoginId(): ?string { return $this->loginId; }
    public function getReportsToPersonId(): ?int { return $this->reportsToPersonId; }
    public function isActive(): bool { return $this->isActive; }

    public function toArray(): array
    {
        return [
            'employment_id' => $this->employmentId,
            'person_id' => $this->personId,
            'employee_code' => $this->employeeCode,
            'department_id' => $this->departmentId,
            'position_id' => $this->positionId,
            'grade_id' => $this->gradeId,
            'employment_type' => $this->employmentType,
            'hire_date' => $this->hireDate,
            'probation_end_date' => $this->probationEndDate,
            'confirmation_date' => $this->confirmationDate,
            'termination_date' => $this->terminationDate,
            'separation_reason_id' => $this->separationReasonId,
            'salary_amount' => $this->salaryAmount,
            'login_id' => $this->loginId,
            'reports_to_person_id' => $this->reportsToPersonId,
            'is_active' => $this->isActive ? 1 : 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function update(array $data): void
    {
        if (isset($data['employee_code'])) $this->employeeCode = $data['employee_code'];
        if (array_key_exists('department_id', $data)) $this->departmentId = $data['department_id'] ? (int)$data['department_id'] : null;
        if (array_key_exists('position_id', $data)) $this->positionId = $data['position_id'] ? (int)$data['position_id'] : null;
        if (array_key_exists('grade_id', $data)) $this->gradeId = $data['grade_id'] ? (int)$data['grade_id'] : null;
        if (isset($data['employment_type'])) $this->employmentType = (int)$data['employment_type'];
        if (isset($data['hire_date'])) $this->hireDate = $data['hire_date'];
        if (isset($data['probation_end_date'])) $this->probationEndDate = $data['probation_end_date'];
        if (isset($data['confirmation_date'])) $this->confirmationDate = $data['confirmation_date'];
        if (isset($data['termination_date'])) $this->terminationDate = $data['termination_date'];
        if (array_key_exists('separation_reason_id', $data)) $this->separationReasonId = $data['separation_reason_id'] ? (int)$data['separation_reason_id'] : null;
        if (isset($data['salary_amount'])) $this->salaryAmount = (float)$data['salary_amount'];
        if (isset($data['login_id'])) $this->loginId = $data['login_id'];
        if (array_key_exists('reports_to_person_id', $data)) $this->reportsToPersonId = $data['reports_to_person_id'] ? (int)$data['reports_to_person_id'] : null;
        if (isset($data['is_active'])) $this->isActive = (bool)$data['is_active'];
        $this->updatedAt = date('Y-m-d H:i:s');
    }
}
