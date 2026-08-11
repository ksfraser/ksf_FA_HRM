<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

/**
 * Links an employee to a customer for sales commission purposes.
 *
 * @BABOK Related: FR-HRM-006
 * @since 1.0.0
 */
class CommissionAssignment
{
    private int $id;
    private int $personId;
    private int $customerId;
    private string $source;
    private bool $isActive;
    private string $createdAt;
    private string $updatedAt;

    public function __construct(array $data)
    {
        $this->id = (int)($data['id'] ?? 0);
        $this->personId = (int)$data['person_id'];
        $this->customerId = (int)$data['customer_id'];
        $this->source = (string)($data['source'] ?? 'all');
        $this->isActive = (bool)($data['is_active'] ?? 1);
        $this->createdAt = (string)($data['created_at'] ?? '');
        $this->updatedAt = (string)($data['updated_at'] ?? '');
    }

    public function getId(): int { return $this->id; }
    public function getPersonId(): int { return $this->personId; }
    public function getCustomerId(): int { return $this->customerId; }
    public function getSource(): string { return $this->source; }
    public function isActive(): bool { return $this->isActive; }
    public function getCreatedAt(): string { return $this->createdAt; }
    public function getUpdatedAt(): string { return $this->updatedAt; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'person_id' => $this->personId,
            'customer_id' => $this->customerId,
            'source' => $this->source,
            'is_active' => $this->isActive ? 1 : 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
