<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Repository;

use ksfraser\FrontAccounting\HRM\Entity\Benefit;
use ksfraser\FrontAccounting\HRM\Entity\EmployeeBenefit;

class BenefitRepository
{
    use FatRepositoryTrait;

    public function findById(int $id): ?Benefit
    {
        $sql = "SELECT * FROM " . TB_PREF . "hrm_benefits WHERE benefit_id = " . $this->intVal($id);
        $row = $this->dbFetchAssoc($this->dbQuery($sql));
        return $row ? new Benefit($row) : null;
    }

    public function findAll(bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM " . TB_PREF . "hrm_benefits";
        if ($activeOnly) $sql .= " WHERE is_active = 1";
        $sql .= " ORDER BY benefit_name";
        return array_map(fn($r) => new Benefit($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    public function save(array $data): int
    {
        $sql = "INSERT INTO " . TB_PREF . "hrm_benefits
            (benefit_name, benefit_code, benefit_type, employer_rate, employee_rate,
             fixed_amount, calculation_period, is_percentage_based,
             gl_code_expense, gl_code_liability, provider,
             is_mandatory, is_tax_deductible, description, is_active)
            VALUES (" .
            $this->escape($data['benefit_name']) . ", " .
            $this->escape($data['benefit_code']) . ", " .
            $this->escape($data['benefit_type'] ?? '') . ", " .
            $this->floatVal($data['employer_rate'] ?? 0) . ", " .
            $this->floatVal($data['employee_rate'] ?? 0) . ", " .
            $this->floatVal($data['fixed_amount'] ?? 0) . ", " .
            $this->escape($data['calculation_period'] ?? 'Monthly') . ", " .
            (isset($data['is_percentage_based']) ? 1 : 0) . ", " .
            $this->escape($data['gl_code_expense'] ?? '') . ", " .
            $this->escape($data['gl_code_liability'] ?? '') . ", " .
            $this->escape($data['provider'] ?? '') . ", " .
            (isset($data['is_mandatory']) ? 1 : 0) . ", " .
            (isset($data['is_tax_deductible']) ? 1 : 0) . ", " .
            $this->escape($data['description'] ?? '') . ", " .
            (isset($data['is_active']) ? 1 : 0) . ")";
        $this->dbQuery($sql);
        return $this->dbInsertId();
    }

    public function deactivate(int $id): void
    {
        $sql = "UPDATE " . TB_PREF . "hrm_benefits SET is_active = 0 WHERE benefit_id = " . $this->intVal($id);
        $this->dbQuery($sql);
    }

    public function findEmployeeBenefits(int $personId): array
    {
        $sql = "SELECT eb.*, b.benefit_name, b.benefit_type, b.is_mandatory
            FROM " . TB_PREF . "hrm_employee_benefits eb
            LEFT JOIN " . TB_PREF . "hrm_benefits b ON eb.benefit_id = b.benefit_id
            WHERE eb.person_id = " . $this->intVal($personId) . "
            AND (eb.end_date IS NULL OR eb.end_date >= CURDATE())
            ORDER BY b.benefit_name";
        return array_map(fn($r) => new EmployeeBenefit($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    public function saveEmployeeBenefit(array $data): int
    {
        $sql = "INSERT INTO " . TB_PREF . "hrm_employee_benefits
            (person_id, benefit_id, effective_date, end_date,
             custom_employer_rate, custom_employee_rate, notes, is_active)
            VALUES (" .
            $this->intVal($data['person_id']) . ", " .
            $this->intVal($data['benefit_id']) . ", " .
            $this->escape($data['effective_date']) . ", " .
            ($data['end_date'] ? $this->escape($data['end_date']) : 'NULL') . ", " .
            ($data['custom_employer_rate'] ?? null !== null ? $this->floatVal($data['custom_employer_rate']) : 'NULL') . ", " .
            ($data['custom_employee_rate'] ?? null !== null ? $this->floatVal($data['custom_employee_rate']) : 'NULL') . ", " .
            $this->escape($data['notes'] ?? '') . ", " .
            (isset($data['is_active']) ? 1 : 0) . ")";
        $this->dbQuery($sql);
        return $this->dbInsertId();
    }
}
