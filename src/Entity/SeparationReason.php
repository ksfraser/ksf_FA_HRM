<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

class SeparationReason
{
    private int $reasonId;
    private string $reasonCode;
    private string $reasonName;
    private ?string $description;
    private bool $isActive;

    public function __construct(array $data)
    {
        $this->reasonId = (int)$data['reason_id'];
        $this->reasonCode = $data['reason_code'];
        $this->reasonName = $data['reason_name'];
        $this->description = $data['description'] ?? null;
        $this->isActive = (bool)($data['is_active'] ?? 1);
    }

    public function getReasonId(): int { return $this->reasonId; }
    public function getReasonCode(): string { return $this->reasonCode; }
    public function getReasonName(): string { return $this->reasonName; }
    public function getDescription(): ?string { return $this->description; }
    public function isActive(): bool { return $this->isActive; }

    public function toArray(): array
    {
        return [
            'reason_id' => $this->reasonId,
            'reason_code' => $this->reasonCode,
            'reason_name' => $this->reasonName,
            'description' => $this->description,
            'is_active' => $this->isActive ? 1 : 0,
        ];
    }
}
