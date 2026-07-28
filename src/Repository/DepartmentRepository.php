<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Repository;

use ksfraser\FrontAccounting\HRM\Entity\Department;

class DepartmentRepository
{
    use FatRepositoryTrait;

    public function findById(int $id): ?Department
    {
        $sql = "SELECT * FROM " . TB_PREF . "hrm_departments WHERE department_id = " . $this->intVal($id);
        $row = $this->dbFetchAssoc($this->dbQuery($sql));
        return $row ? new Department($row) : null;
    }

    public function findAll(string $orderBy = 'department_code'): array
    {
        $sql = "SELECT * FROM " . TB_PREF . "hrm_departments ORDER BY " . $orderBy;
        return array_map(fn($r) => new Department($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    public function findActive(): array
    {
        $sql = "SELECT * FROM " . TB_PREF . "hrm_departments WHERE is_active = 1 ORDER BY department_name";
        return array_map(fn($r) => new Department($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    public function save(array $data): int
    {
        $sql = "INSERT INTO " . TB_PREF . "hrm_departments
            (department_code, department_name, description, is_active)
            VALUES (" .
            $this->escape($data['department_code']) . ", " .
            $this->escape($data['department_name']) . ", " .
            $this->escape($data['description'] ?? '') . ", " .
            (isset($data['is_active']) ? 1 : 0) . ")";
        $this->dbQuery($sql);
        return $this->dbInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sets = [];
        foreach (['department_code', 'department_name', 'description'] as $field) {
            if (isset($data[$field])) {
                $sets[] = "`$field` = " . $this->escape($data[$field]);
            }
        }
        if (isset($data['is_active'])) {
            $sets[] = "`is_active` = " . ($data['is_active'] ? 1 : 0);
        }
        if (empty($sets)) return;
        $sql = "UPDATE " . TB_PREF . "hrm_departments SET " . implode(', ', $sets) .
            " WHERE department_id = " . $this->intVal($id);
        $this->dbQuery($sql);
    }

    public function delete(int $id): void
    {
        $sql = "DELETE FROM " . TB_PREF . "hrm_departments WHERE department_id = " . $this->intVal($id);
        $this->dbQuery($sql);
    }
}
