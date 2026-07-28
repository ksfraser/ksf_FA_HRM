<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Repository;

use ksfraser\FrontAccounting\HRM\Entity\Grade;

class GradeRepository
{
    use FatRepositoryTrait;

    public function findById(int $id): ?Grade
    {
        $sql = "SELECT * FROM " . TB_PREF . "hrm_grades WHERE grade_id = " . $this->intVal($id);
        $row = $this->dbFetchAssoc($this->dbQuery($sql));
        return $row ? new Grade($row) : null;
    }

    public function findActive(): array
    {
        $sql = "SELECT * FROM " . TB_PREF . "hrm_grades WHERE is_active = 1 ORDER BY grade_name";
        return array_map(fn($r) => new Grade($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM " . TB_PREF . "hrm_grades ORDER BY grade_name";
        return array_map(fn($r) => new Grade($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    public function save(array $data): int
    {
        $sql = "INSERT INTO " . TB_PREF . "hrm_grades
            (grade_code, grade_name, min_salary, max_salary, description, is_active)
            VALUES (" .
            $this->escape($data['grade_code']) . ", " .
            $this->escape($data['grade_name']) . ", " .
            $this->floatVal($data['min_salary'] ?? 0) . ", " .
            $this->floatVal($data['max_salary'] ?? 0) . ", " .
            $this->escape($data['description'] ?? '') . ", " .
            (isset($data['is_active']) ? 1 : 0) . ")";
        $this->dbQuery($sql);
        return $this->dbInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sets = [];
        foreach (['grade_code', 'grade_name', 'description'] as $field) {
            if (isset($data[$field])) {
                $sets[] = "`$field` = " . $this->escape($data[$field]);
            }
        }
        foreach (['min_salary', 'max_salary'] as $field) {
            if (isset($data[$field])) {
                $sets[] = "`$field` = " . $this->floatVal($data[$field]);
            }
        }
        if (isset($data['is_active'])) {
            $sets[] = "`is_active` = " . ($data['is_active'] ? 1 : 0);
        }
        if (empty($sets)) return;
        $sql = "UPDATE " . TB_PREF . "hrm_grades SET " . implode(', ', $sets) .
            " WHERE grade_id = " . $this->intVal($id);
        $this->dbQuery($sql);
    }
}
