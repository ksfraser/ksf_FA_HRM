<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Repository;

use ksfraser\FrontAccounting\HRM\Entity\Team;

class TeamRepository
{
    use FatRepositoryTrait;

    public function findById(int $id): ?Team
    {
        $sql = "SELECT * FROM " . TB_PREF . "hrm_teams WHERE team_id = " . $this->intVal($id);
        $row = $this->dbFetchAssoc($this->dbQuery($sql));
        return $row ? new Team($row) : null;
    }

    public function findByDepartment(int $departmentId): array
    {
        $sql = "SELECT t.*, COALESCE(parent.team_name, '') AS parent_team_name
            FROM " . TB_PREF . "hrm_teams t
            LEFT JOIN " . TB_PREF . "hrm_teams parent ON t.parent_team_id = parent.team_id
            WHERE t.department_id = " . $this->intVal($departmentId) .
            " ORDER BY parent.team_name, t.team_name";
        return array_map(fn($r) => new Team($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    public function findActiveByDepartment(int $departmentId): array
    {
        $sql = "SELECT * FROM " . TB_PREF . "hrm_teams
            WHERE department_id = " . $this->intVal($departmentId) . " AND is_active = 1
            ORDER BY team_name";
        return array_map(fn($r) => new Team($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    public function findAll(): array
    {
        $sql = "SELECT t.*, d.department_code, d.department_name,
                COALESCE(parent.team_name, '') AS parent_team_name
            FROM " . TB_PREF . "hrm_teams t
            LEFT JOIN " . TB_PREF . "hrm_departments d ON t.department_id = d.department_id
            LEFT JOIN " . TB_PREF . "hrm_teams parent ON t.parent_team_id = parent.team_id
            ORDER BY d.department_code, parent.team_name, t.team_name";
        return array_map(fn($r) => new Team($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    public function save(array $data): int
    {
        $parent = (int)($data['parent_team_id'] ?? 0);
        $sql = "INSERT INTO " . TB_PREF . "hrm_teams
            (department_id, parent_team_id, team_code, team_name, description, is_active)
            VALUES (" .
            $this->intVal($data['department_id']) . ", " .
            ($parent > 0 ? $parent : 'NULL') . ", " .
            $this->escape($data['team_code']) . ", " .
            $this->escape($data['team_name']) . ", " .
            $this->escape($data['description'] ?? '') . ", " .
            (isset($data['is_active']) ? 1 : 0) . ")";
        $this->dbQuery($sql);
        return $this->dbInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sets = [];
        if (array_key_exists('parent_team_id', $data)) {
            $parent = (int)$data['parent_team_id'];
            $sets[] = "`parent_team_id` = " . ($parent > 0 ? $parent : 'NULL');
        }
        foreach (['team_code', 'team_name', 'description'] as $field) {
            if (isset($data[$field])) {
                $sets[] = "`$field` = " . $this->escape($data[$field]);
            }
        }
        if (isset($data['is_active'])) {
            $sets[] = "`is_active` = " . ($data['is_active'] ? 1 : 0);
        }
        if (empty($sets)) return;
        $sql = "UPDATE " . TB_PREF . "hrm_teams SET " . implode(', ', $sets) .
            " WHERE team_id = " . $this->intVal($id);
        $this->dbQuery($sql);
    }

    public function delete(int $id): void
    {
        $sql = "DELETE FROM " . TB_PREF . "hrm_teams WHERE team_id = " . $this->intVal($id);
        $this->dbQuery($sql);
    }
}
