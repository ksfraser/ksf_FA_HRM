<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Repository;

use ksfraser\FrontAccounting\HRM\Entity\Employee;

class EmployeeRepository
{
    use FatRepositoryTrait;

    private string $baseSql = "SELECT e.*, d.department_name, d.department_code,
        p.position_code, r.role_name, tm.team_name,
        CONCAT(COALESCE(cp.firstname, ''), ' ', COALESCE(cp.lastname, '')) AS person_name
        FROM " . TB_PREF . "hrm_contacts_employment e
        LEFT JOIN " . TB_PREF . "hrm_departments d ON e.department_id = d.department_id
        LEFT JOIN " . TB_PREF . "hrm_positions p ON e.position_id = p.position_id
        LEFT JOIN " . TB_PREF . "hrm_roles r ON p.role_id = r.role_id
        LEFT JOIN " . TB_PREF . "hrm_teams tm ON p.team_id = tm.team_id
        LEFT JOIN " . TB_PREF . "crm_persons cp ON e.person_id = cp.id";

    public function findById(int $id): ?Employee
    {
        $sql = $this->baseSql . " WHERE e.employment_id = " . $this->intVal($id);
        $row = $this->dbFetchAssoc($this->dbQuery($sql));
        return $row ? new Employee($row) : null;
    }

    public function findByPersonId(int $personId): ?Employee
    {
        $sql = $this->baseSql . " WHERE e.person_id = " . $this->intVal($personId);
        $row = $this->dbFetchAssoc($this->dbQuery($sql));
        return $row ? new Employee($row) : null;
    }

    public function findAll(): array
    {
        $sql = $this->baseSql . " ORDER BY e.employee_code ASC";
        return array_map(fn($r) => new Employee($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    public function findActive(): array
    {
        $sql = $this->baseSql . " WHERE e.is_active = 1 ORDER BY e.employee_code ASC";
        return array_map(fn($r) => new Employee($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    public function save(array $data): int
    {
        $sql = "INSERT INTO " . TB_PREF . "hrm_contacts_employment
            (person_id, employee_code, department_id, position_id, grade_id,
             reports_to_person_id, hire_date, probation_end_date, is_active)
            VALUES (" .
            $this->intVal($data['person_id']) . ", " .
            $this->escape($data['employee_code']) . ", " .
            $this->intVal($data['department_id'] ?? 0) . ", " .
            $this->intVal($data['position_id'] ?? 0) . ", " .
            $this->intVal($data['grade_id'] ?? 0) . ", " .
            $this->intVal($data['reports_to_person_id'] ?? 0) . ", " .
            $this->escape($data['hire_date'] ?? null) . ", " .
            $this->escape($data['probation_end_date'] ?? null) . ", " .
            (isset($data['is_active']) ? 1 : 0) . ")";
        $this->dbQuery($sql);
        return $this->dbInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sets = [];
        foreach (['employee_code', 'hire_date', 'probation_end_date', 'confirmation_date', 'termination_date'] as $field) {
            if (isset($data[$field])) {
                $sets[] = "`$field` = " . $this->escape($data[$field]);
            }
        }
        foreach (['person_id', 'department_id', 'position_id', 'grade_id', 'reports_to_person_id', 'employment_type', 'separation_reason_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "`$field` = " . $this->intVal($data[$field]);
            }
        }
        if (isset($data['salary_amount'])) {
            $sets[] = "`salary_amount` = " . $this->floatVal($data['salary_amount']);
        }
        if (isset($data['is_active'])) {
            $sets[] = "`is_active` = " . ($data['is_active'] ? 1 : 0);
        }
        if (empty($sets)) return;
        $sql = "UPDATE " . TB_PREF . "hrm_contacts_employment SET " . implode(', ', $sets) .
            " WHERE employment_id = " . $this->intVal($id);
        $this->dbQuery($sql);
    }

    public function delete(int $id): void
    {
        $sql = "DELETE FROM " . TB_PREF . "hrm_contacts_employment WHERE employment_id = " . $this->intVal($id);
        $this->dbQuery($sql);
    }
}
