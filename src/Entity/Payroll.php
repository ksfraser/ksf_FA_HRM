<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

class Payroll
{
    private int $payrollId;
    private int $personId;
    private string $payPeriodStart;
    private string $payPeriodEnd;
    private float $grossPay;
    private float $totalDeductions;
    private float $netPay;
    private string $payDate;
    private string $status;
    private bool $glPosted;
    private ?string $employeeName;
    private ?string $employeeCode;

    public function __construct(array $data)
    {
        $this->payrollId = (int)$data['payroll_id'];
        $this->personId = (int)$data['person_id'];
        $this->payPeriodStart = $data['pay_period_start'];
        $this->payPeriodEnd = $data['pay_period_end'];
        $this->grossPay = (float)($data['gross_pay'] ?? 0);
        $this->totalDeductions = (float)($data['total_deductions'] ?? 0);
        $this->netPay = (float)($data['net_pay'] ?? 0);
        $this->payDate = $data['pay_date'];
        $this->status = $data['status'] ?? 'Draft';
        $this->glPosted = (bool)($data['gl_posted'] ?? 0);
        $this->employeeName = $data['employee_name'] ?? null;
        $this->employeeCode = $data['employee_code'] ?? null;
    }

    public function getPayrollId(): int { return $this->payrollId; }
    public function getPersonId(): int { return $this->personId; }
    public function getPayPeriodStart(): string { return $this->payPeriodStart; }
    public function getPayPeriodEnd(): string { return $this->payPeriodEnd; }
    public function getGrossPay(): float { return $this->grossPay; }
    public function getTotalDeductions(): float { return $this->totalDeductions; }
    public function getNetPay(): float { return $this->netPay; }
    public function getPayDate(): string { return $this->payDate; }
    public function getStatus(): string { return $this->status; }
    public function isGlPosted(): bool { return $this->glPosted; }
    public function getEmployeeName(): ?string { return $this->employeeName; }
    public function getEmployeeCode(): ?string { return $this->employeeCode; }

    public function toArray(): array
    {
        return [
            'payroll_id' => $this->payrollId,
            'person_id' => $this->personId,
            'pay_period_start' => $this->payPeriodStart,
            'pay_period_end' => $this->payPeriodEnd,
            'gross_pay' => $this->grossPay,
            'total_deductions' => $this->totalDeductions,
            'net_pay' => $this->netPay,
            'pay_date' => $this->payDate,
            'status' => $this->status,
            'gl_posted' => $this->glPosted ? 1 : 0,
            'employee_name' => $this->employeeName,
            'employee_code' => $this->employeeCode,
        ];
    }

    public function update(array $data): void
    {
        if (isset($data['gross_pay'])) $this->grossPay = (float)$data['gross_pay'];
        if (isset($data['total_deductions'])) $this->totalDeductions = (float)$data['total_deductions'];
        if (isset($data['net_pay'])) $this->netPay = (float)$data['net_pay'];
        if (isset($data['status'])) $this->status = $data['status'];
        if (isset($data['gl_posted'])) $this->glPosted = (bool)$data['gl_posted'];
    }
}
