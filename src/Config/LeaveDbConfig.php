<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Config;

class LeaveDbConfig
{
    /**
     * Leave database configuration
     *
     * @var array Configuration for leave queries
     */
    private array $config = [
        'table_prefix' => TB_PREF . 'hrm_',
        'fields' => [
            'leave_type_id' => 'leave_type_id',
            'leave_type_name' => 'leave_type_name',
            'description' => 'description',
            'is_active' => 'is_active',
        ],
    ];

    public function getTableName(string $table): string
    {
        return $this->config['table_prefix'] . $table;
    }

    public function getField(string $field): string
    {
        return $this->config['fields'][$field] ?? $field;
    }

    public function getFields(): array
    {
        return array_values($this->config['fields']);
    }
}