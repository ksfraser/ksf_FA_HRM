<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

class EmploymentStatus
{
    private int $statusId;
    private string $statusCode;
    private string $statusName;
    private bool $isActive;

    public function __construct(array $data)
    {
        $this->statusId = (int)$data['status_id'];
        $this->statusCode = $data['status_code'];
        $this->statusName = $data['status_name'];
        $this->isActive = (bool)($data['is_active'] ?? 1);
    }

    public function getStatusId(): int { return $this->statusId; }
    public function getStatusCode(): string { return $this->statusCode; }
    public function getStatusName(): string { return $this->statusName; }
    public function isActive(): bool { return $this->isActive; }

    public function toArray(): array
    {
        return [
            'status_id' => $this->statusId,
            'status_code' => $this->statusCode,
            'status_name' => $this->statusName,
            'is_active' => $this->isActive ? 1 : 0,
        ];
    }
}
