<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

class RoleDictionary
{
    /** @var int */
    private $roleDictId;
    /** @var string */
    private $roleName;
    /** @var string|null */
    private $description;
    /** @var bool */
    private $isActive;
    /** @var string */
    private $createdAt;

    public function __construct(array $data)
    {
        $this->roleDictId = (int)$data['role_dict_id'];
        $this->roleName = $data['role_name'];
        $this->description = $data['description'] ?? null;
        $this->isActive = (bool)($data['is_active'] ?? 1);
        $this->createdAt = $data['created_at'] ?? '';
    }

    public function getRoleDictId(): int { return $this->roleDictId; }
    public function getRoleName(): string { return $this->roleName; }
    public function getDescription(): ?string { return $this->description; }
    public function isActive(): bool { return $this->isActive; }

    public function toArray(): array
    {
        return [
            'role_dict_id' => $this->roleDictId,
            'role_name' => $this->roleName,
            'description' => $this->description,
            'is_active' => $this->isActive ? 1 : 0,
            'created_at' => $this->createdAt,
        ];
    }
}
