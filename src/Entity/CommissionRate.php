<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

/**
 * Commission rate configuration for an employee.
 *
 * @BABOK Related: FR-HRM-004
 * @since 1.0.0
 */
class CommissionRate
{
    private int $rateId;
    private int $personId;
    private string $source;
    private string $rateType;
    private float $rate;
    private string $effectiveFrom;
    private ?string $effectiveTo;
    private bool $isActive;
    private string $createdAt;
    private string $updatedAt;

    public function __construct(array $data)
    {
        $this->rateId = (int)($data['rate_id'] ?? 0);
        $this->personId = (int)$data['person_id'];
        $this->source = (string)($data['source'] ?? 'all');
        $this->rateType = (string)($data['rate_type'] ?? 'percent');
        $this->rate = (float)($data['rate'] ?? 0);
        $this->effectiveFrom = (string)($data['effective_from'] ?? '');
        $this->effectiveTo = $data['effective_to'] ?? null;
        $this->isActive = (bool)($data['is_active'] ?? 1);
        $this->createdAt = (string)($data['created_at'] ?? '');
        $this->updatedAt = (string)($data['updated_at'] ?? '');
    }

    public function getRateId(): int { return $this->rateId; }
    public function getPersonId(): int { return $this->personId; }
    public function getSource(): string { return $this->source; }
    public function getRateType(): string { return $this->rateType; }
    public function getRate(): float { return $this->rate; }
    public function getEffectiveFrom(): string { return $this->effectiveFrom; }
    public function getEffectiveTo(): ?string { return $this->effectiveTo; }
    public function isActive(): bool { return $this->isActive; }
    public function getCreatedAt(): string { return $this->createdAt; }
    public function getUpdatedAt(): string { return $this->updatedAt; }

    public function toArray(): array
    {
        return [
            'rate_id' => $this->rateId,
            'person_id' => $this->personId,
            'source' => $this->source,
            'rate_type' => $this->rateType,
            'rate' => $this->rate,
            'effective_from' => $this->effectiveFrom,
            'effective_to' => $this->effectiveTo,
            'is_active' => $this->isActive ? 1 : 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
