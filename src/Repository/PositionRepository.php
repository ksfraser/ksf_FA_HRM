<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Repository;

use ksfraser\FrontAccounting\HRM\Entity\Position;

class PositionRepository
{
    use FatRepositoryTrait;

    private string $baseSql = "SELECT p.*, d.department_code, t.team_code, r.role_name
        FROM " . TB_PREF . "hrm_positions p
        LEFT JOIN " . TB_PREF . "hrm_departments d ON p.department_id = d.department_id
        LEFT JOIN " . TB_PREF . "hrm_teams t ON p.team_id = t.team_id
        LEFT JOIN " . TB_PREF . "hrm_roles r ON p.role_id = r.role_id";

    public function findById(int $id): ?Position
    {
        $sql = $this->baseSql . " WHERE p.position_id = " . $this->intVal($id);
        $row = $this->dbFetchAssoc($this->dbQuery($sql));
        return $row ? new Position($row) : null;
    }

    public function findByDepartment(int $departmentId): array
    {
        $sql = $this->baseSql . " WHERE p.department_id = " . $this->intVal($departmentId) .
            " ORDER BY p.position_code";
        return array_map(fn($r) => new Position($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    public function findActive(): array
    {
        $sql = $this->baseSql . " WHERE p.is_active = 1 ORDER BY p.position_code";
        return array_map(fn($r) => new Position($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    public function generatePositionCode(int $departmentId, ?int $teamId): string
    {
        $deptRow = $this->dbFetchAssoc($this->dbQuery(
            "SELECT department_code FROM " . TB_PREF . "hrm_departments WHERE department_id = " . $this->intVal($departmentId)
        ));
        $teamRow = $teamId ? $this->dbFetchAssoc($this->dbQuery(
            "SELECT team_code FROM " . TB_PREF . "hrm_teams WHERE team_id = " . $this->intVal($teamId)
        )) : null;

        $deptCode = $deptRow ? strtoupper(substr($deptRow['department_code'], 0, 3)) : 'UNK';
        $teamCode = $teamRow ? strtoupper(substr($teamRow['team_code'], 0, 3)) : 'GEN';

        $sql = "SELECT MAX(position_number) AS max_num FROM " . TB_PREF . "hrm_positions
            WHERE department_id = " . $this->intVal($departmentId) .
            " AND team_id " . ($teamId ? "= " . $this->intVal($teamId) : "IS NULL");
        $result = $this->dbFetchAssoc($this->dbQuery($sql));
        $next = ($result && $result['max_num']) ? (int)$result['max_num'] + 1 : 1;

        return $deptCode . '-' . $teamCode . '-' . str_pad($next, 3, '0', STR_PAD_LEFT);
    }

    public function save(array $data): int
    {
        $teamId = (int)($data['team_id'] ?? 0);
        $code = $this->generatePositionCode($data['department_id'], $teamId > 0 ? $teamId : null);

        $sql = "INSERT INTO " . TB_PREF . "hrm_positions
            (position_code, department_id, team_id, role_id, position_number, description, is_active)
            VALUES (" .
            $this->escape($code) . ", " .
            $this->intVal($data['department_id']) . ", " .
            ($teamId > 0 ? $this->intVal($teamId) : 'NULL') . ", " .
            $this->intVal($data['role_id']) . ", " .
            $this->intVal($data['position_number'] ?? 1) . ", " .
            $this->escape($data['description'] ?? '') . ", " .
            (isset($data['is_active']) ? 1 : 0) . ")";
        $this->dbQuery($sql);
        return $this->dbInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sets = [];
        $teamId = (int)($data['team_id'] ?? 0);
        if (isset($data['department_id'])) $sets[] = "`department_id` = " . $this->intVal($data['department_id']);
        $sets[] = "`team_id` = " . ($teamId > 0 ? $this->intVal($teamId) : 'NULL');
        if (isset($data['role_id'])) $sets[] = "`role_id` = " . $this->intVal($data['role_id']);
        if (isset($data['description'])) $sets[] = "`description` = " . $this->escape($data['description']);
        if (isset($data['is_active'])) $sets[] = "`is_active` = " . ($data['is_active'] ? 1 : 0);
        if (empty($sets)) return;
        $sql = "UPDATE " . TB_PREF . "hrm_positions SET " . implode(', ', $sets) .
            " WHERE position_id = " . $this->intVal($id);
        $this->dbQuery($sql);
    }
}
