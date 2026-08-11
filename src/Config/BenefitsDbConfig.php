<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Config;

class BenefitsDbConfig
{
    /**
     * Benefits database configuration
     *
     * @var array Configuration for benefits queries
     */
    private array $config = [
        'table_prefix' => TB_PREF . 'hrm_',
        'fields' => [
            'benefit_id' => 'benefit_id',
            'benefit_name' => 'benefit_name',
            'benefit_type' => 'benefit_type',
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
