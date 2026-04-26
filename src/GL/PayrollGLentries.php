<?php

declare(strict_types=1);

namespace Ksf\FA\HRM\GL;

use Ksf\HRM\Entity\EmployeeCompensation;
use Ksf\HRM\Entity\Benefit;
use Ksf\HRM\Service\CompensationService;

class PayrollGLentries
{
    private CompensationService $compensationService;

    public function __construct(?CompensationService $compensationService = null)
    {
        $this->compensationService = $compensationService ?? new CompensationService();
    }

    public function createJournalEntry(int $employeeId, array $glEntries, string $description, ?int $reference = null): array
    {
        return [
            'date' => date('Y-m-d'),
            'reference' => $reference ?? $this->generateReference(),
            'description' => $description,
            'employee_id' => $employeeId,
            'lines' => $glEntries,
            'total_debit' => array_sum(array_column(array_filter($glEntries, fn($e) => $e['type'] === 'expense'), 'amount')),
            'total_credit' => array_sum(array_column(array_filter($glEntries, fn($e) => $e['type'] === 'liability'), 'amount')),
        ];
    }

    public function postPayrollToGL(array $journalEntry): bool
    {
        global $db_connections;
        
        $this->ensureGLtableExists();
        
        $result = write_journal(
            $journalEntry['date'],
            $journalEntry['reference'],
            $journalEntry['description'],
            $journalEntry['lines'],
            $journalEntry['total_debit'],
            $journalEntry['total_credit']
        );

        return $result;
    }

    private function generateReference(): string
    {
        return 'PR-' . date('Ym') . '-' . str_pad((string)(rand(1, 999)), 3, '0', STR_PAD_LEFT);
    }

    private function ensureGLtableExists(): void
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS " . TB_PREF . "ksf_hrm_payroll (
            id INT NOT NULL AUTO_INCREMENT,
            employee_id INT NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            gross_pay DECIMAL(15,2) NOT NULL,
            net_pay DECIMAL(15,2) NOT NULL,
            gl_reference VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY employee_id (employee_id),
            KEY period (period_start, period_end)
        )";
        db_query($sql, 'Could not create payroll table');
    }

    public function getGLCodeMapping(): array
    {
        return [
            'salary_expense' => get_company_pref('ksf_salary_expense_gl') ?? 'G01',
            'ot_expense' => get_company_pref('ksf_ot_expense_gl') ?? 'O01',
            'vacation_expense' => get_company_pref('ksf_vacation_expense_gl') ?? 'V01',
            'sick_expense' => get_company_pref('ksf_sick_expense_gl') ?? 'S01',
            'ei_expense' => get_company_pref('ksf_ei_expense_gl') ?? '2200',
            'cpp_expense' => get_company_pref('ksf_cpp_expense_gl') ?? '2210',
            'ei_liability' => get_company_pref('ksf_ei_liability_gl') ?? '2300',
            'cpp_liability' => get_company_pref('ksf_cpp_liability_gl') ?? '2310',
            'pension_expense' => get_company_pref('ksf_pension_expense_gl') ?? '2400',
            'pension_liability' => get_company_pref('ksf_pension_liability_gl') ?? '2410',
            'health_expense' => get_company_pref('ksf_health_expense_gl') ?? '2500',
            'health_liability' => get_company_pref('ksf_health_liability_gl') ?? '2510',
        ];
    }
}