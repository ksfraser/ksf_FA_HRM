<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Repository;

use ksfraser\FrontAccounting\HRM\Entity\EmploymentStatus;

class LookupRepository
{
    use FatRepositoryTrait;

    public function getEmploymentStatuses(): array
    {
        $sql = "SELECT * FROM " . TB_PREF . "hrm_employment_status ORDER BY status_name";
        return array_map(function ($r) { return new EmploymentStatus($r); }, $this->dbFetchAll($this->dbQuery($sql)));
    }

    public function getLeaveTypes(): array
    {
        $sql = "SELECT * FROM " . TB_PREF . "leave_types ORDER BY type_name";
        return $this->dbFetchAll($this->dbQuery($sql));
    }

    public function saveLeaveType(array $data): int
    {
        $sql = "INSERT INTO " . TB_PREF . "leave_types
            (type_code, type_name, default_days, is_paid, is_active)
            VALUES (" .
            $this->escape($data['type_code']) . ", " .
            $this->escape($data['type_name']) . ", " .
            $this->floatVal($data['default_days'] ?? 0) . ", " .
            (isset($data['is_paid']) ? 1 : 0) . ", " .
            (isset($data['is_active']) ? 1 : 0) . ")";
        $this->dbQuery($sql);
        return $this->dbInsertId();
    }

    /**
     * Update an existing leave type.
     *
     * @param int   $id   leave_type_id
     * @param array $data Field values; only the keys present are written
     *
     * @since 1.0.0
     */
    public function updateLeaveType(int $id, array $data): void
    {
        $sets = [];
        foreach (['type_code', 'type_name'] as $field) {
            if (isset($data[$field])) {
                $sets[] = "`$field` = " . $this->escape($data[$field]);
            }
        }
        if (isset($data['default_days'])) {
            $sets[] = "`default_days` = " . $this->floatVal($data['default_days']);
        }
        foreach (['is_paid', 'is_active'] as $flag) {
            if (isset($data[$flag])) {
                $sets[] = "`$flag` = " . ($data[$flag] ? 1 : 0);
            }
        }
        if (empty($sets)) {
            return;
        }
        $sql = "UPDATE " . TB_PREF . "leave_types SET " . implode(', ', $sets) .
            " WHERE leave_type_id = " . $this->intVal($id);
        $this->dbQuery($sql);
    }

    /**
     * Soft-delete a leave type: clear is_active only, so historic leave
     * requests keep resolving their type_name via the join in leave.php.
     *
     * @param int $id leave_type_id
     *
     * @since 1.0.0
     */
    public function deactivateLeaveType(int $id): void
    {
        $sql = "UPDATE " . TB_PREF . "leave_types SET is_active = 0 WHERE leave_type_id = " . $this->intVal($id);
        $this->dbQuery($sql);
    }

    public function getCrmPersons(): array
    {
        $sql = "SELECT id, name FROM " . TB_PREF . "crm_persons ORDER BY name";
        return $this->dbFetchAll($this->dbQuery($sql));
    }

    public function getSeparationReasons(): array
    {
        $sql = "SELECT * FROM " . TB_PREF . "hrm_separation_reasons WHERE is_active = 1 ORDER BY reason_name";
        return $this->dbFetchAll($this->dbQuery($sql));
    }
}
