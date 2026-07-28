<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

class Department
{
    private int $departmentId;
    private ?string $departmentCode;
    private string $departmentName;
    private ?int $managerPersonId;
    private ?int $parentDepartmentId;
    private ?int $costCenterId;
    private ?string $description;
    private bool $isActive;
    private string $createdAt;
    private string $updatedAt;

    public function __construct(array $data)
    {
        $this->departmentId = (int)$data['department_id'];
        $this->departmentCode = $data['department_code'] ?? null;
        $this->departmentName = $data['department_name'];
        $this->managerPersonId = $data['manager_person_id'] ? (int)$data['manager_person_id'] : null;
        $this->parentDepartmentId = $data['parent_department_id'] ? (int)$data['parent_department_id'] : null;
        $this->costCenterId = $data['cost_center_id'] ? (int)$data['cost_center_id'] : null;
        $this->description = $data['description'] ?? null;
        $this->isActive = (bool)($data['is_active'] ?? 1);
        $this->createdAt = $data['created_at'] ?? '';
        $this->updatedAt = $data['updated_at'] ?? '';
    }

    public function getDepartmentId(): int { return $this->departmentId; }
    public function getDepartmentCode(): ?string { return $this->departmentCode; }
    public function getDepartmentName(): string { return $this->departmentName; }
    public function getManagerPersonId(): ?int { return $this->managerPersonId; }
    public function getParentDepartmentId(): ?int { return $this->parentDepartmentId; }
    public function getCostCenterId(): ?int { return $this->costCenterId; }
    public function getDescription(): ?string { return $this->description; }
    public function isActive(): bool { return $this->isActive; }
    public function getCreatedAt(): string { return $this->createdAt; }
    public function getUpdatedAt(): string { return $this->updatedAt; }

    public function toArray(): array
    {
        return [
            'department_id' => $this->departmentId,
            'department_code' => $this->departmentCode,
            'department_name' => $this->departmentName,
            'manager_person_id' => $this->managerPersonId,
            'parent_department_id' => $this->parentDepartmentId,
            'cost_center_id' => $this->costCenterId,
            'description' => $this->description,
            'is_active' => $this->isActive ? 1 : 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function update(array $data): void
    {
        if (isset($data['department_code'])) $this->departmentCode = $data['department_code'];
        if (isset($data['department_name'])) $this->departmentName = $data['department_name'];
        if (array_key_exists('manager_person_id', $data)) $this->managerPersonId = $data['manager_person_id'] ? (int)$data['manager_person_id'] : null;
        if (array_key_exists('parent_department_id', $data)) $this->parentDepartmentId = $data['parent_department_id'] ? (int)$data['parent_department_id'] : null;
        if (array_key_exists('cost_center_id', $data)) $this->costCenterId = $data['cost_center_id'] ? (int)$data['cost_center_id'] : null;
        if (isset($data['description'])) $this->description = $data['description'];
        if (isset($data['is_active'])) $this->isActive = (bool)$data['is_active'];
        $this->updatedAt = date('Y-m-d H:i:s');
    }
}
