<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

class PayRateHistory
{
    private int $rateId;
    private int $employmentId;
    private ?int $assignmentId;
    private float $oldSalary;
    private float $newSalary;
    private float $oldHourlyRate;
    private float $newHourlyRate;
    private string $effectiveDate;
    private ?string $reason;
    private ?int $approvedByPersonId;
    private string $approvalStatus;

    public function __construct(array $data)
    {
        $this->rateId = (int)$data['rate_id'];
        $this->employmentId = (int)$data['employment_id'];
        $this->assignmentId = $data['assignment_id'] ? (int)$data['assignment_id'] : null;
        $this->oldSalary = (float)($data['old_salary'] ?? 0);
        $this->newSalary = (float)($data['new_salary'] ?? 0);
        $this->oldHourlyRate = (float)($data['old_hourly_rate'] ?? 0);
        $this->newHourlyRate = (float)($data['new_hourly_rate'] ?? 0);
        $this->effectiveDate = $data['effective_date'];
        $this->reason = $data['reason'] ?? null;
        $this->approvedByPersonId = $data['approved_by_person_id'] ? (int)$data['approved_by_person_id'] : null;
        $this->approvalStatus = $data['approval_status'] ?? 'Approved';
    }

    public function getRateId(): int { return $this->rateId; }
    public function getEmploymentId(): int { return $this->employmentId; }
    public function getAssignmentId(): ?int { return $this->assignmentId; }
    public function getOldSalary(): float { return $this->oldSalary; }
    public function getNewSalary(): float { return $this->newSalary; }
    public function getOldHourlyRate(): float { return $this->oldHourlyRate; }
    public function getNewHourlyRate(): float { return $this->newHourlyRate; }
    public function getEffectiveDate(): string { return $this->effectiveDate; }
    public function getReason(): ?string { return $this->reason; }

    public function toArray(): array
    {
        return [
            'rate_id' => $this->rateId,
            'employment_id' => $this->employmentId,
            'assignment_id' => $this->assignmentId,
            'old_salary' => $this->oldSalary,
            'new_salary' => $this->newSalary,
            'old_hourly_rate' => $this->oldHourlyRate,
            'new_hourly_rate' => $this->newHourlyRate,
            'effective_date' => $this->effectiveDate,
            'reason' => $this->reason,
            'approved_by_person_id' => $this->approvedByPersonId,
            'approval_status' => $this->approvalStatus,
        ];
    }
}
