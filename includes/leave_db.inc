<?php
/**
 * DB functions for ksf_hrm_leave_balances table
 */

function write_leave_balance($balance_id, $data) {
    if ($balance_id) {
        $sql = "UPDATE `" . TB_PREF . "ksf_hrm_leave_balances` SET ";
        $sets = array();
        foreach (array('person_id', 'leave_type_id', 'year', 'allocated_days', 'used_days', 'carried_over') as $field) {
            if (isset($data[$field])) {
                $sets[] = "`$field` = " . db_escape($data[$field]);
            }
        }
        $sql .= implode(', ', $sets) . " WHERE `balance_id` = " . db_escape($balance_id);
    } else {
        $sql = "INSERT INTO `" . TB_PREF . "ksf_hrm_leave_balances` SET ";
        $sets = array();
        foreach (array('person_id', 'leave_type_id', 'year', 'allocated_days', 'used_days', 'carried_over') as $field) {
            if (isset($data[$field])) {
                $sets[] = "`$field` = " . db_escape($data[$field]);
            }
        }
        $sql .= implode(', ', $sets);
    }
    
    db_query($sql, "Failed to write leave balance");
    return $balance_id ? $balance_id : db_insert_id();
}

function get_leave_balance($balance_id) {
    $sql = "SELECT lb.*, lt.leave_type_name, p.name as employee_name
        FROM `" . TB_PREF . "ksf_hrm_leave_balances` lb
        LEFT JOIN `" . TB_PREF . "leave_types` lt ON lb.leave_type_id = lt.id
        LEFT JOIN `" . TB_PREF . "crm_persons` p ON lb.person_id = p.id
        WHERE lb.`balance_id` = " . db_escape($balance_id);
    $result = db_query($sql, "Failed to get leave balance");
    return db_fetch($result);
}

function get_employee_leave_balances($person_id, $year = null) {
    if (!$year) {
        $year = date('Y');
    }
    $sql = "SELECT lb.*, lt.leave_type_name
        FROM `" . TB_PREF . "ksf_hrm_leave_balances` lb
        LEFT JOIN `" . TB_PREF . "leave_types` lt ON lb.leave_type_id = lt.id
        WHERE lb.`person_id` = " . db_escape($person_id) . "
        AND lb.`year` = " . (int)$year . "
        ORDER BY lt.leave_type_name";
    return db_query($sql, "Failed to get employee leave balances");
}

function update_leave_usage($person_id, $leave_type_id, $days, $year = null) {
    if (!$year) {
        $year = date('Y');
    }
    $sql = "UPDATE `" . TB_PREF . "ksf_hrm_leave_balances` 
        SET `used_days` = `used_days` + " . db_escape($days) . "
        WHERE `person_id` = " . db_escape($person_id) . "
        AND `leave_type_id` = " . db_escape($leave_type_id) . "
        AND `year` = " . (int)$year;
    db_query($sql, "Failed to update leave usage");
}

function delete_leave_balance($balance_id) {
    $sql = "DELETE FROM `" . TB_PREF . "ksf_hrm_leave_balances` WHERE `balance_id` = " . db_escape($balance_id);
    db_query($sql, "Failed to delete leave balance");
}
?>
