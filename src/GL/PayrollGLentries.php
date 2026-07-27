<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\GL;

/**
 * Payroll GL Entry Generator
 *
 * Creates journal entries for payroll processing and posts them
 * to FA's general ledger via write_journal().
 *
 * GL code mapping is configurable per company via preferences:
 * - ksf_salary_expense_gl
 * - ksf_ot_expense_gl
 * - ksf_vacation_expense_gl
 * - ksf_sick_expense_gl
 * - ei/cpp/pension/health expense and liability accounts
 *
 * @package ksfraser\FrontAccounting\HRM
 * @since 1.0.0
 */
class PayrollGLentries
{
    /**
     * Create a journal entry array for a payroll run.
     *
     * @param int $employeeId The employee's person_id
     * @param array $glEntries Array of GL line items with type/amount/account
     * @param string $description Journal entry description
     * @param int|null $reference Optional reference number
     * @return array Structured journal entry ready for write_journal()
     */
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

    /**
     * Post a payroll journal entry to FA's GL.
     *
     * @param array $journalEntry Structured journal entry
     * @return bool True on success
     */
    public function postPayrollToGL(array $journalEntry): bool
    {
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

    /**
     * Generate a unique reference for payroll entries.
     */
    private function generateReference(): string
    {
        return 'PR-' . date('Ym') . '-' . str_pad((string)(rand(1, 999)), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Get the GL code mapping for payroll accounts.
     *
     * @return array Associative array of payroll account types to GL codes
     */
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
