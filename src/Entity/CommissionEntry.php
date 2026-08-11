<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

/**
 * A commission entry generated for an employee on an imported order.
 *
 * @BABOK Related: FR-HRM-005
 * @since 1.0.0
 */
class CommissionEntry
{
    private int $entryId;
    private int $personId;
    private int $faOrderNo;
    private int $faTransType;
    private string $source;
    private ?string $sourceOrderId;
    private ?int $customerId;
    private float $orderTotal;
    private float $commissionAmount;
    private float $rate;
    private string $status;
    private string $orderDate;
    private string $createdAt;

    public function __construct(array $data)
    {
        $this->entryId = (int)($data['entry_id'] ?? 0);
        $this->personId = (int)($data['person_id'] ?? 0);
        $this->faOrderNo = (int)($data['fa_order_no'] ?? 0);
        $this->faTransType = (int)($data['fa_trans_type'] ?? 10);
        $this->source = (string)($data['source'] ?? 'all');
        $this->sourceOrderId = $data['source_order_id'] ?? null;
        $this->customerId = isset($data['customer_id']) && $data['customer_id'] !== null && $data['customer_id'] !== '' ? (int)$data['customer_id'] : null;
        $this->orderTotal = (float)($data['order_total'] ?? 0);
        $this->commissionAmount = (float)($data['commission_amount'] ?? 0);
        $this->rate = (float)($data['rate'] ?? 0);
        $this->status = (string)($data['status'] ?? 'pending');
        $this->orderDate = (string)($data['order_date'] ?? '');
        $this->createdAt = (string)($data['created_at'] ?? '');
    }

    public function getEntryId(): int { return $this->entryId; }
    public function getPersonId(): int { return $this->personId; }
    public function getFaOrderNo(): int { return $this->faOrderNo; }
    public function getFaTransType(): int { return $this->faTransType; }
    public function getSource(): string { return $this->source; }
    public function getSourceOrderId(): ?string { return $this->sourceOrderId; }
    public function getCustomerId(): ?int { return $this->customerId; }
    public function getOrderTotal(): float { return $this->orderTotal; }
    public function getCommissionAmount(): float { return $this->commissionAmount; }
    public function getRate(): float { return $this->rate; }
    public function getStatus(): string { return $this->status; }
    public function getOrderDate(): string { return $this->orderDate; }
    public function getCreatedAt(): string { return $this->createdAt; }

    public function toArray(): array
    {
        return [
            'entry_id' => $this->entryId,
            'person_id' => $this->personId,
            'fa_order_no' => $this->faOrderNo,
            'fa_trans_type' => $this->faTransType,
            'source' => $this->source,
            'source_order_id' => $this->sourceOrderId,
            'customer_id' => $this->customerId,
            'order_total' => $this->orderTotal,
            'commission_amount' => $this->commissionAmount,
            'rate' => $this->rate,
            'status' => $this->status,
            'order_date' => $this->orderDate,
            'created_at' => $this->createdAt,
        ];
    }
}
