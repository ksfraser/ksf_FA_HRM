<?php

$module = 'employee';
$target_fields = [
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
];

return [
    'name' => 'ksf_FA_HRM',
    'title' => 'HRM Import',
    'fields' => $target_fields,
    'processor' => function($row) use ($module) {
        global $db;
        include_once '../../ksf_FA_HRM/includes/db.inc';
        
        $emp_no = $row['emp_no'] ?? '';
        
        if (empty($emp_no)) {
            return false;
        }
        
        $check = db_fetch_assoc(db_query(
            "SELECT emp_no FROM " . TB_PREF . "ksf_employee WHERE emp_no = " . db_escape($emp_no)
        ));
        
        if ($check) {
            $sets = [];
            foreach ($row as $f => $v) {
                if ($f !== 'emp_no') {
                    $sets[] = "$f = " . db_escape($v);
                }
            }
            $sql = "UPDATE " . TB_PREF . "ksf_employee SET " . implode(', ', $sets) . " WHERE emp_no = " . db_escape($emp_no);
        } else {
            $cols = implode(', ', array_keys($row));
            $vals = implode(', ', array_map(fn($v) => db_escape($v), array_values($row)));
            $sql = "INSERT INTO " . TB_PREF . "ksf_employee ($cols) VALUES ($vals)";
        }
        
        db_query($sql);
        
        return ['emp_no' => $emp_no, 'action' => $check ? 'updated' : 'created'];
    },
];