<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Repository;

use ksfraser\FrontAccounting\HRM\Entity\PayElement;

class PayElementRepository
{
    use FatRepositoryTrait;

    public function findById(int $id): ?PayElement
    {
        $sql = "SELECT * FROM " . TB_PREF . "hrm_pay_elements WHERE element_id = " . $this->intVal($id);
        $row = $this->dbFetchAssoc($this->dbQuery($sql));
        return $row ? new PayElement($row) : null;
    }

    public function findActive(): array
    {
        $sql = "SELECT * FROM " . TB_PREF . "hrm_pay_elements WHERE is_active = 1 ORDER BY element_code";
        return array_map(fn($r) => new PayElement($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    public function findByCategory(string $category): array
    {
        $sql = "SELECT * FROM " . TB_PREF . "hrm_pay_elements
            WHERE category = " . $this->escape($category) . " AND is_active = 1
            ORDER BY element_code";
        return array_map(fn($r) => new PayElement($r), $this->dbFetchAll($this->dbQuery($sql)));
    }

    public function save(array $data): int
    {
        $sql = "INSERT INTO " . TB_PREF . "hrm_pay_elements
            (element_code, element_name, category, calculation_type, default_value, gl_account_code, is_active)
            VALUES (" .
            $this->escape($data['element_code']) . ", " .
            $this->escape($data['element_name']) . ", " .
            $this->escape($data['category']) . ", " .
            $this->escape($data['calculation_type'] ?? 'fixed') . ", " .
            $this->floatVal($data['default_value'] ?? 0) . ", " .
            $this->escape($data['gl_account_code'] ?? '') . ", " .
            (isset($data['is_active']) ? 1 : 0) . ")";
        $this->dbQuery($sql);
        return $this->dbInsertId();
    }
}
