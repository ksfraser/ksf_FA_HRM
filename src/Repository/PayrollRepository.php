<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Repository;

use ksfraser\FrontAccounting\HRM\Entity\Payroll;
use ksfraser\FrontAccounting\HRM\Entity\PayrollEntry;

class PayrollRepository
{
    use FatRepositoryTrait;

    public function findById(int $id): ?Payroll
    {
        $sql = "SELECT p.*, per.name AS employee_name, ce.employee_code
            FROM " . TB_PREF . "hrm_payroll p
            LEFT JOIN " . TB_PREF . "crm_persons per ON p.person_id = per.id
            LEFT JOIN " . TB_PREF . "hrm_contacts_employment ce ON p.person_id = ce.person_id
            WHERE p.payroll_id = " . $this->intVal($id);
        $row = $this->dbFetchAssoc($this->dbQuery($sql));
        return $row ? new Payroll($row) : null;
    }

    public function findAll(): array
    {
        $sql = "SELECT p.*, c.name AS employee_name
            FROM " . TB_PREF . "hrm_payroll p
            LEFT JOIN " . TB_PREF . "crm_persons c ON p.person_id = c.id
            ORDER BY p.pay_period_start DESC";
        return array_map(fn($r) => new Payroll($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    public function findByPerson(int $personId, int $limit = 12): array
    {
        $sql = "SELECT * FROM " . TB_PREF . "hrm_payroll
            WHERE person_id = " . $this->intVal($personId) . "
            ORDER BY pay_period_end DESC LIMIT " . $this->intVal($limit);
        return array_map(fn($r) => new Payroll($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    public function save(array $data): int
    {
        $sql = "INSERT INTO " . TB_PREF . "hrm_payroll
            (person_id, pay_period_start, pay_period_end, gross_pay, total_deductions, net_pay, pay_date, status, gl_posted)
            VALUES (" .
            $this->intVal($data['person_id']) . ", " .
            $this->escape($data['pay_period_start']) . ", " .
            $this->escape($data['pay_period_end']) . ", " .
            $this->floatVal($data['gross_pay'] ?? 0) . ", " .
            $this->floatVal($data['total_deductions'] ?? 0) . ", " .
            $this->floatVal($data['net_pay'] ?? 0) . ", " .
            $this->escape($data['pay_date']) . ", " .
            $this->escape($data['status'] ?? 'Draft') . ", " .
            ($data['gl_posted'] ?? 0 ? 1 : 0) . ")";
        $this->dbQuery($sql);
        return $this->dbInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sets = [];
        foreach (['person_id', 'pay_period_start', 'pay_period_end', 'pay_date', 'status'] as $field) {
            if (isset($data[$field])) $sets[] = "`$field` = " . $this->escape($data[$field]);
        }
        foreach (['gross_pay', 'total_deductions', 'net_pay'] as $field) {
            if (isset($data[$field])) $sets[] = "`$field` = " . $this->floatVal($data[$field]);
        }
        if (isset($data['gl_posted'])) $sets[] = "`gl_posted` = " . ($data['gl_posted'] ? 1 : 0);
        if (empty($sets)) return;
        $sql = "UPDATE " . TB_PREF . "hrm_payroll SET " . implode(', ', $sets) .
            " WHERE payroll_id = " . $this->intVal($id);
        $this->dbQuery($sql);
    }

    public function delete(int $id): void
    {
        $this->dbQuery("DELETE FROM " . TB_PREF . "hrm_payroll_entries WHERE payroll_id = " . $this->intVal($id));
        $this->dbQuery("DELETE FROM " . TB_PREF . "hrm_payroll WHERE payroll_id = " . $this->intVal($id));
    }

    public function getEntries(int $payrollId): array
    {
        $sql = "SELECT pe.*, e.element_name, e.element_code, e.category
            FROM " . TB_PREF . "hrm_payroll_entries pe
            LEFT JOIN " . TB_PREF . "hrm_pay_elements e ON pe.element_id = e.element_id
            WHERE pe.payroll_id = " . $this->intVal($payrollId) . "
            ORDER BY e.element_code";
        return array_map(fn($r) => new PayrollEntry($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    public function saveEntry(array $data): int
    {
        $sql = "INSERT INTO " . TB_PREF . "hrm_payroll_entries
            (payroll_id, element_id, amount, note)
            VALUES (" .
            $this->intVal($data['payroll_id']) . ", " .
            $this->intVal($data['element_id']) . ", " .
            $this->floatVal($data['amount'] ?? 0) . ", " .
            $this->escape($data['note'] ?? '') . ")";
        $this->dbQuery($sql);
        return $this->dbInsertId();
    }
}
