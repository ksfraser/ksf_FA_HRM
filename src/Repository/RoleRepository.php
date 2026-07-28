<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Repository;

use ksfraser\FrontAccounting\HRM\Entity\Role;
use ksfraser\FrontAccounting\HRM\Entity\RoleDictionary;

class RoleRepository
{
    use FatRepositoryTrait;

    public function findDictionary(): array
    {
        $sql = "SELECT * FROM " . TB_PREF . "hrm_role_dictionary ORDER BY role_name";
        return array_map(fn($r) => new RoleDictionary($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    public function findById(int $id): ?Role
    {
        $sql = "SELECT * FROM " . TB_PREF . "hrm_roles WHERE role_id = " . $this->intVal($id);
        $row = $this->dbFetchAssoc($this->dbQuery($sql));
        return $row ? new Role($row) : null;
    }

    public function findByDepartment(int $departmentId): array
    {
        $sql = "SELECT r.*, rd.role_name AS dict_name
            FROM " . TB_PREF . "hrm_roles r
            LEFT JOIN " . TB_PREF . "hrm_role_dictionary rd ON r.role_dict_id = rd.role_dict_id
            WHERE r.department_id = " . $this->intVal($departmentId) .
            " ORDER BY r.role_name";
        return array_map(fn($r) => new Role($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    public function save(array $data): int
    {
        $sql = "INSERT INTO " . TB_PREF . "hrm_roles
            (department_id, role_dict_id, role_name, description, is_active)
            VALUES (" .
            $this->intVal($data['department_id']) . ", " .
            $this->intVal($data['role_dict_id'] ?? 0) . ", " .
            $this->escape($data['role_name']) . ", " .
            $this->escape($data['description'] ?? '') . ", " .
            (isset($data['is_active']) ? 1 : 0) . ")";
        $this->dbQuery($sql);
        return $this->dbInsertId();
    }
}
