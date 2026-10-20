<?php

$module = 'employee';
$target_fields = array(
    'emp_no',
    'first_name',
    'last_name',
    'email',
    'phone',
    'department',
    'position',
    'location',
    'joined_date',
    'salary',
    'hourly_rate',
);

return array(
    'name' => 'ksf_FA_HRM',
    'title' => 'HRM Import',
    'fields' => $target_fields,
    'processor' => function($row) use ($module) {
        // The employee is an FA person plus a row in HRM's own employment
        // table; see includes/employee_import.inc for why.
        require_once __DIR__ . '/includes/employee_import.inc';

        return hrm_import_employee_row($row);
    },
);