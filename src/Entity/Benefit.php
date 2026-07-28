<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

class Benefit
{
    private int $benefitId;
    private string $benefitCode;
    private string $benefitName;
    private ?string $benefitType;
    private float $employerRate;
    private float $employeeRate;
    private float $fixedAmount;
    private bool $isPercentageBased;
    private string $calculationPeriod;
    private ?string $glCodeExpense;
    private ?string $glCodeLiability;
    private ?string $provider;
    private bool $isMandatory;
    private bool $isTaxDeductible;
    private ?string $description;
    private bool $isActive;
    private string $createdAt;
    private string $updatedAt;

    public function __construct(array $data)
    {
        $this->benefitId = (int)$data['benefit_id'];
        $this->benefitCode = $data['benefit_code'];
        $this->benefitName = $data['benefit_name'];
        $this->benefitType = $data['benefit_type'] ?? null;
        $this->employerRate = (float)($data['employer_rate'] ?? 0);
        $this->employeeRate = (float)($data['employee_rate'] ?? 0);
        $this->fixedAmount = (float)($data['fixed_amount'] ?? 0);
        $this->isPercentageBased = (bool)($data['is_percentage_based'] ?? 1);
        $this->calculationPeriod = $data['calculation_period'] ?? 'Monthly';
        $this->glCodeExpense = $data['gl_code_expense'] ?? null;
        $this->glCodeLiability = $data['gl_code_liability'] ?? null;
        $this->provider = $data['provider'] ?? null;
        $this->isMandatory = (bool)($data['is_mandatory'] ?? 0);
        $this->isTaxDeductible = (bool)($data['is_tax_deductible'] ?? 0);
        $this->description = $data['description'] ?? null;
        $this->isActive = (bool)($data['is_active'] ?? 1);
        $this->createdAt = $data['created_at'] ?? '';
        $this->updatedAt = $data['updated_at'] ?? '';
    }

    public function getBenefitId(): int { return $this->benefitId; }
    public function getBenefitCode(): string { return $this->benefitCode; }
    public function getBenefitName(): string { return $this->benefitName; }
    public function getBenefitType(): ?string { return $this->benefitType; }
    public function getEmployerRate(): float { return $this->employerRate; }
    public function getEmployeeRate(): float { return $this->employeeRate; }
    public function getFixedAmount(): float { return $this->fixedAmount; }
    public function isPercentageBased(): bool { return $this->isPercentageBased; }
    public function getCalculationPeriod(): string { return $this->calculationPeriod; }
    public function getGlCodeExpense(): ?string { return $this->glCodeExpense; }
    public function getGlCodeLiability(): ?string { return $this->glCodeLiability; }
    public function getProvider(): ?string { return $this->provider; }
    public function isMandatory(): bool { return $this->isMandatory; }
    public function isTaxDeductible(): bool { return $this->isTaxDeductible; }
    public function getDescription(): ?string { return $this->description; }
    public function isActive(): bool { return $this->isActive; }

    public function toArray(): array
    {
        return [
            'benefit_id' => $this->benefitId,
            'benefit_code' => $this->benefitCode,
            'benefit_name' => $this->benefitName,
            'benefit_type' => $this->benefitType,
            'employer_rate' => $this->employerRate,
            'employee_rate' => $this->employeeRate,
            'fixed_amount' => $this->fixedAmount,
            'is_percentage_based' => $this->isPercentageBased ? 1 : 0,
            'calculation_period' => $this->calculationPeriod,
            'gl_code_expense' => $this->glCodeExpense,
            'gl_code_liability' => $this->glCodeLiability,
            'provider' => $this->provider,
            'is_mandatory' => $this->isMandatory ? 1 : 0,
            'is_tax_deductible' => $this->isTaxDeductible ? 1 : 0,
            'description' => $this->description,
            'is_active' => $this->isActive ? 1 : 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function update(array $data): void
    {
        if (isset($data['benefit_code'])) $this->benefitCode = $data['benefit_code'];
        if (isset($data['benefit_name'])) $this->benefitName = $data['benefit_name'];
        if (isset($data['benefit_type'])) $this->benefitType = $data['benefit_type'];
        if (isset($data['employer_rate'])) $this->employerRate = (float)$data['employer_rate'];
        if (isset($data['employee_rate'])) $this->employeeRate = (float)$data['employee_rate'];
        if (isset($data['fixed_amount'])) $this->fixedAmount = (float)$data['fixed_amount'];
        if (isset($data['is_percentage_based'])) $this->isPercentageBased = (bool)$data['is_percentage_based'];
        if (isset($data['calculation_period'])) $this->calculationPeriod = $data['calculation_period'];
        if (isset($data['gl_code_expense'])) $this->glCodeExpense = $data['gl_code_expense'];
        if (isset($data['gl_code_liability'])) $this->glCodeLiability = $data['gl_code_liability'];
        if (isset($data['provider'])) $this->provider = $data['provider'];
        if (isset($data['is_mandatory'])) $this->isMandatory = (bool)$data['is_mandatory'];
        if (isset($data['is_tax_deductible'])) $this->isTaxDeductible = (bool)$data['is_tax_deductible'];
        if (isset($data['description'])) $this->description = $data['description'];
        if (isset($data['is_active'])) $this->isActive = (bool)$data['is_active'];
        $this->updatedAt = date('Y-m-d H:i:s');
    }
}
