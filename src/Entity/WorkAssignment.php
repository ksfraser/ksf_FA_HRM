<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

class WorkAssignment
{
    private int $assignmentId;
    private int $employmentId;
    private int $positionId;
    private ?int $gradeId;
    private float $salaryAmount;
    private float $hourlyRate;
    private string $payFrequency;
    private string $effectiveDate;
    private ?string $endDate;
    private bool $isCurrent;
    private ?string $reason;
    private ?int $approvedByPersonId;
    private string $approvalStatus;

    public function __construct(array $data)
    {
        $this->assignmentId = (int)$data['assignment_id'];
        $this->employmentId = (int)$data['employment_id'];
        $this->positionId = (int)$data['position_id'];
        $this->gradeId = $data['grade_id'] ? (int)$data['grade_id'] : null;
        $this->salaryAmount = (float)($data['salary_amount'] ?? 0);
        $this->hourlyRate = (float)($data['hourly_rate'] ?? 0);
        $this->payFrequency = $data['pay_frequency'] ?? 'Monthly';
        $this->effectiveDate = $data['effective_date'];
        $this->endDate = $data['end_date'] ?? null;
        $this->isCurrent = (bool)($data['is_current'] ?? 1);
        $this->reason = $data['reason'] ?? null;
        $this->approvedByPersonId = $data['approved_by_person_id'] ? (int)$data['approved_by_person_id'] : null;
        $this->approvalStatus = $data['approval_status'] ?? 'Approved';
    }

    public function getAssignmentId(): int { return $this->assignmentId; }
    public function getEmploymentId(): int { return $this->employmentId; }
    public function getPositionId(): int { return $this->positionId; }
    public function getGradeId(): ?int { return $this->gradeId; }
    public function getSalaryAmount(): float { return $this->salaryAmount; }
    public function getHourlyRate(): float { return $this->hourlyRate; }
    public function getPayFrequency(): string { return $this->payFrequency; }
    public function getEffectiveDate(): string { return $this->effectiveDate; }
    public function getEndDate(): ?string { return $this->endDate; }
    public function isCurrent(): bool { return $this->isCurrent; }
    public function getReason(): ?string { return $this->reason; }

    public function toArray(): array
    {
        return [
            'assignment_id' => $this->assignmentId,
            'employment_id' => $this->employmentId,
            'position_id' => $this->positionId,
            'grade_id' => $this->gradeId,
            'salary_amount' => $this->salaryAmount,
            'hourly_rate' => $this->hourlyRate,
            'pay_frequency' => $this->payFrequency,
            'effective_date' => $this->effectiveDate,
            'end_date' => $this->endDate,
            'is_current' => $this->isCurrent ? 1 : 0,
            'reason' => $this->reason,
            'approved_by_person_id' => $this->approvedByPersonId,
            'approval_status' => $this->approvalStatus,
        ];
    }
}
