<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

class EmployeeBenefit
{
    private int $id;
    private int $personId;
    private int $benefitId;
    private string $effectiveDate;
    private ?string $endDate;
    private ?float $customEmployerRate;
    private ?float $customEmployeeRate;
    private ?string $notes;
    private bool $isActive;
    private string $createdAt;

    public function __construct(array $data)
    {
        $this->id = (int)$data['id'];
        $this->personId = (int)$data['person_id'];
        $this->benefitId = (int)$data['benefit_id'];
        $this->effectiveDate = $data['effective_date'];
        $this->endDate = $data['end_date'] ?? null;
        $this->customEmployerRate = isset($data['custom_employer_rate']) ? (float)$data['custom_employer_rate'] : null;
        $this->customEmployeeRate = isset($data['custom_employee_rate']) ? (float)$data['custom_employee_rate'] : null;
        $this->notes = $data['notes'] ?? null;
        $this->isActive = (bool)($data['is_active'] ?? 1);
        $this->createdAt = $data['created_at'] ?? '';
    }

    public function getId(): int { return $this->id; }
    public function getPersonId(): int { return $this->personId; }
    public function getBenefitId(): int { return $this->benefitId; }
    public function getEffectiveDate(): string { return $this->effectiveDate; }
    public function getEndDate(): ?string { return $this->endDate; }
    public function getCustomEmployerRate(): ?float { return $this->customEmployerRate; }
    public function getCustomEmployeeRate(): ?float { return $this->customEmployeeRate; }
    public function getNotes(): ?string { return $this->notes; }
    public function isActive(): bool { return $this->isActive; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'person_id' => $this->personId,
            'benefit_id' => $this->benefitId,
            'effective_date' => $this->effectiveDate,
            'end_date' => $this->endDate,
            'custom_employer_rate' => $this->customEmployerRate,
            'custom_employee_rate' => $this->customEmployeeRate,
            'notes' => $this->notes,
            'is_active' => $this->isActive ? 1 : 0,
            'created_at' => $this->createdAt,
        ];
    }

    public function update(array $data): void
    {
        if (isset($data['effective_date'])) $this->effectiveDate = $data['effective_date'];
        if (isset($data['end_date'])) $this->endDate = $data['end_date'];
        if (array_key_exists('custom_employer_rate', $data)) $this->customEmployerRate = $data['custom_employer_rate'] !== null ? (float)$data['custom_employer_rate'] : null;
        if (array_key_exists('custom_employee_rate', $data)) $this->customEmployeeRate = $data['custom_employee_rate'] !== null ? (float)$data['custom_employee_rate'] : null;
        if (isset($data['notes'])) $this->notes = $data['notes'];
        if (isset($data['is_active'])) $this->isActive = (bool)$data['is_active'];
    }
}
