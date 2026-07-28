<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

class Position
{
    private int $positionId;
    private string $positionCode;
    private int $departmentId;
    private ?int $teamId;
    private int $roleId;
    private int $positionNumber;
    private ?string $description;
    private bool $isActive;
    private string $createdAt;
    private string $updatedAt;
    private ?string $departmentCode;
    private ?string $teamCode;
    private ?string $roleName;

    public function __construct(array $data)
    {
        $this->positionId = (int)$data['position_id'];
        $this->positionCode = $data['position_code'];
        $this->departmentId = (int)$data['department_id'];
        $this->teamId = $data['team_id'] ? (int)$data['team_id'] : null;
        $this->roleId = (int)$data['role_id'];
        $this->positionNumber = (int)($data['position_number'] ?? 1);
        $this->description = $data['description'] ?? null;
        $this->isActive = (bool)($data['is_active'] ?? 1);
        $this->createdAt = $data['created_at'] ?? '';
        $this->updatedAt = $data['updated_at'] ?? '';
        $this->departmentCode = $data['department_code'] ?? null;
        $this->teamCode = $data['team_code'] ?? null;
        $this->roleName = $data['role_name'] ?? null;
    }

    public function getPositionId(): int { return $this->positionId; }
    public function getPositionCode(): string { return $this->positionCode; }
    public function getDepartmentId(): int { return $this->departmentId; }
    public function getTeamId(): ?int { return $this->teamId; }
    public function getRoleId(): int { return $this->roleId; }
    public function getPositionNumber(): int { return $this->positionNumber; }
    public function getDescription(): ?string { return $this->description; }
    public function isActive(): bool { return $this->isActive; }
    public function getDepartmentCode(): ?string { return $this->departmentCode; }
    public function getTeamCode(): ?string { return $this->teamCode; }
    public function getRoleName(): ?string { return $this->roleName; }

    public function toArray(): array
    {
        return [
            'position_id' => $this->positionId,
            'position_code' => $this->positionCode,
            'department_id' => $this->departmentId,
            'team_id' => $this->teamId,
            'role_id' => $this->roleId,
            'position_number' => $this->positionNumber,
            'description' => $this->description,
            'is_active' => $this->isActive ? 1 : 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'department_code' => $this->departmentCode,
            'team_code' => $this->teamCode,
            'role_name' => $this->roleName,
        ];
    }

    public function update(array $data): void
    {
        if (isset($data['department_id'])) $this->departmentId = (int)$data['department_id'];
        if (array_key_exists('team_id', $data)) $this->teamId = $data['team_id'] ? (int)$data['team_id'] : null;
        if (isset($data['role_id'])) $this->roleId = (int)$data['role_id'];
        if (isset($data['description'])) $this->description = $data['description'];
        if (isset($data['is_active'])) $this->isActive = (bool)$data['is_active'];
        $this->updatedAt = date('Y-m-d H:i:s');
    }
}
