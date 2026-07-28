<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

class PayrollEntry
{
    private int $entryId;
    private int $payrollId;
    private int $elementId;
    private float $amount;
    private ?string $note;

    public function __construct(array $data)
    {
        $this->entryId = (int)$data['entry_id'];
        $this->payrollId = (int)$data['payroll_id'];
        $this->elementId = (int)$data['element_id'];
        $this->amount = (float)($data['amount'] ?? 0);
        $this->note = $data['note'] ?? null;
    }

    public function getEntryId(): int { return $this->entryId; }
    public function getPayrollId(): int { return $this->payrollId; }
    public function getElementId(): int { return $this->elementId; }
    public function getAmount(): float { return $this->amount; }
    public function getNote(): ?string { return $this->note; }

    public function toArray(): array
    {
        return [
            'entry_id' => $this->entryId,
            'payroll_id' => $this->payrollId,
            'element_id' => $this->elementId,
            'amount' => $this->amount,
            'note' => $this->note,
        ];
    }
}
