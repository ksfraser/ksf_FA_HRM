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
        return array_map(fn($r) => new EmploymentStatus($r), $this->dbFetchAll($this->dbQuery($sql)));
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
