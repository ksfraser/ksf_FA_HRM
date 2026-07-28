<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

class Team
{
    private int $teamId;
    private int $departmentId;
    private ?int $parentTeamId;
    private string $teamCode;
    private string $teamName;
    private ?string $description;
    private bool $isActive;
    private string $createdAt;
    private string $updatedAt;

    public function __construct(array $data)
    {
        $this->teamId = (int)$data['team_id'];
        $this->departmentId = (int)$data['department_id'];
        $this->parentTeamId = $data['parent_team_id'] ? (int)$data['parent_team_id'] : null;
        $this->teamCode = $data['team_code'];
        $this->teamName = $data['team_name'];
        $this->description = $data['description'] ?? null;
        $this->isActive = (bool)($data['is_active'] ?? 1);
        $this->createdAt = $data['created_at'] ?? '';
        $this->updatedAt = $data['updated_at'] ?? '';
    }

    public function getTeamId(): int { return $this->teamId; }
    public function getDepartmentId(): int { return $this->departmentId; }
    public function getParentTeamId(): ?int { return $this->parentTeamId; }
    public function getTeamCode(): string { return $this->teamCode; }
    public function getTeamName(): string { return $this->teamName; }
    public function getDescription(): ?string { return $this->description; }
    public function isActive(): bool { return $this->isActive; }
    public function getCreatedAt(): string { return $this->createdAt; }
    public function getUpdatedAt(): string { return $this->updatedAt; }

    public function toArray(): array
    {
        return [
            'team_id' => $this->teamId,
            'department_id' => $this->departmentId,
            'parent_team_id' => $this->parentTeamId,
            'team_code' => $this->teamCode,
            'team_name' => $this->teamName,
            'description' => $this->description,
            'is_active' => $this->isActive ? 1 : 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function update(array $data): void
    {
        if (array_key_exists('parent_team_id', $data)) $this->parentTeamId = $data['parent_team_id'] ? (int)$data['parent_team_id'] : null;
        if (isset($data['team_code'])) $this->teamCode = $data['team_code'];
        if (isset($data['team_name'])) $this->teamName = $data['team_name'];
        if (isset($data['description'])) $this->description = $data['description'];
        if (isset($data['is_active'])) $this->isActive = (bool)$data['is_active'];
        $this->updatedAt = date('Y-m-d H:i:s');
    }
}
