<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

class PayPeriod
{
    private int $periodId;
    private string $periodName;
    private string $periodStart;
    private string $periodEnd;
    private string $payDate;
    private string $frequency;
    private string $status;

    public function __construct(array $data)
    {
        $this->periodId = (int)$data['period_id'];
        $this->periodName = $data['period_name'];
        $this->periodStart = $data['period_start'];
        $this->periodEnd = $data['period_end'];
        $this->payDate = $data['pay_date'];
        $this->frequency = $data['frequency'];
        $this->status = $data['status'] ?? 'Open';
    }

    public function getPeriodId(): int { return $this->periodId; }
    public function getPeriodName(): string { return $this->periodName; }
    public function getPeriodStart(): string { return $this->periodStart; }
    public function getPeriodEnd(): string { return $this->periodEnd; }
    public function getPayDate(): string { return $this->payDate; }
    public function getFrequency(): string { return $this->frequency; }
    public function getStatus(): string { return $this->status; }

    public function toArray(): array
    {
        return [
            'period_id' => $this->periodId,
            'period_name' => $this->periodName,
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'pay_date' => $this->payDate,
            'frequency' => $this->frequency,
            'status' => $this->status,
        ];
    }
}
