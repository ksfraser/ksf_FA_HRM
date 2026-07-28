<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

class Role
{
    private int $roleId;
    private int $departmentId;
    private ?int $roleDictId;
    private string $roleName;
    private ?string $description;
    private bool $isActive;
    private string $createdAt;
    private string $updatedAt;

    public function __construct(array $data)
    {
        $this->roleId = (int)$data['role_id'];
        $this->departmentId = (int)$data['department_id'];
        $this->roleDictId = $data['role_dict_id'] ? (int)$data['role_dict_id'] : null;
        $this->roleName = $data['role_name'];
        $this->description = $data['description'] ?? null;
        $this->isActive = (bool)($data['is_active'] ?? 1);
        $this->createdAt = $data['created_at'] ?? '';
        $this->updatedAt = $data['updated_at'] ?? '';
    }

    public function getRoleId(): int { return $this->roleId; }
    public function getDepartmentId(): int { return $this->departmentId; }
    public function getRoleDictId(): ?int { return $this->roleDictId; }
    public function getRoleName(): string { return $this->roleName; }
    public function getDescription(): ?string { return $this->description; }
    public function isActive(): bool { return $this->isActive; }

    public function toArray(): array
    {
        return [
            'role_id' => $this->roleId,
            'department_id' => $this->departmentId,
            'role_dict_id' => $this->roleDictId,
            'role_name' => $this->roleName,
            'description' => $this->description,
            'is_active' => $this->isActive ? 1 : 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function update(array $data): void
    {
        if (isset($data['role_name'])) $this->roleName = $data['role_name'];
        if (isset($data['description'])) $this->description = $data['description'];
        if (isset($data['is_active'])) $this->isActive = (bool)$data['is_active'];
        $this->updatedAt = date('Y-m-d H:i:s');
    }
}
