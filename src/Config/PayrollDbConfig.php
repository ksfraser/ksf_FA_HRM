<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Config;

class PayrollDbConfig
{
    /**
     * Payroll database configuration
     *
     * @var array Configuration for payroll queries
     */
    private array $config = [
        'table_prefix' => TB_PREF . 'hrm_',
        'fields' => [
            'employment_id' => 'employment_id',
            'person_id' => 'person_id',
            'employee_code' => 'employee_code',
            'department_id' => 'department_id',
            'position_id' => 'position_id',
            'grade_id' => 'grade_id',
            'reports_to_person_id' => 'reports_to_person_id',
            'hire_date' => 'hire_date',
            'probation_end_date' => 'probation_end_date',
            'salary_amount' => 'salary_amount',
            'is_active' => 'is_active',
        ],
        'joins' => [
            'departments' => [
                'table' => 'hrm_departments',
                'local' => 'department_id',
                'foreign' => 'department_id',
            ],
            'positions' => [
                'table' => 'hrm_positions',
                'local' => 'position_id',
                'foreign' => 'position_id',
            ],
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

    public function getJoin(string $join): array
    {
        return $this->config['joins'][$join] ?? [];
    }

    public function getFields(): array
    {
        return array_values($this->config['fields']);
    }
}
