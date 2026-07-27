<?php
/**
 * DB functions for ksf_hrm_payroll and ksf_hrm_payroll_entries tables
 */

function write_payroll($payroll_id, $data) {
    if ($payroll_id) {
        $sql = "UPDATE `" . TB_PREF . "ksf_hrm_payroll` SET ";
        $sets = array();
        foreach (array('person_id', 'pay_period_start', 'pay_period_end', 'gross_pay', 'total_deductions', 'net_pay', 'pay_date', 'status', 'gl_posted') as $field) {
            if (isset($data[$field])) {
                $sets[] = "`$field` = " . db_escape($data[$field]);
            }
        }
        $sql .= implode(', ', $sets) . " WHERE `payroll_id` = " . db_escape($payroll_id);
    } else {
        $sql = "INSERT INTO `" . TB_PREF . "ksf_hrm_payroll` SET ";
        $sets = array();
        foreach (array('person_id', 'pay_period_start', 'pay_period_end', 'gross_pay', 'total_deductions', 'net_pay', 'pay_date', 'status', 'gl_posted') as $field) {
            if (isset($data[$field])) {
                $sets[] = "`$field` = " . db_escape($data[$field]);
            }
        }
        $sql .= implode(', ', $sets);
    }
    
    db_query($sql, "Failed to write payroll");
    return $payroll_id ? $payroll_id : db_insert_id();
}

function get_payroll($payroll_id) {
    $sql = "SELECT p.*, per.name as employee_name, ce.employee_code
        FROM `" . TB_PREF . "ksf_hrm_payroll` p
        LEFT JOIN `" . TB_PREF . "crm_persons` per ON p.person_id = per.id
        LEFT JOIN `" . TB_PREF . "hrm_contacts_employment` ce ON p.person_id = ce.person_id
        WHERE p.`payroll_id` = " . db_escape($payroll_id);
    $result = db_query($sql, "Failed to get payroll");
    return db_fetch($result);
}

function get_employee_payroll_history($person_id, $limit = 12) {
    $sql = "SELECT * FROM `" . TB_PREF . "ksf_hrm_payroll` 
        WHERE `person_id` = " . db_escape($person_id) . "
        ORDER BY `pay_period_end` DESC
        LIMIT " . (int)$limit;
    return db_query($sql, "Failed to get payroll history");
}

function delete_payroll($payroll_id) {
    $sql = "DELETE FROM `" . TB_PREF . "ksf_hrm_payroll_entries` WHERE `payroll_id` = " . db_escape($payroll_id);
    db_query($sql, "Failed to delete payroll entries");
    
    $sql = "DELETE FROM `" . TB_PREF . "ksf_hrm_payroll` WHERE `payroll_id` = " . db_escape($payroll_id);
    db_query($sql, "Failed to delete payroll");
}

function write_payroll_entry($entry_id, $data) {
    if ($entry_id) {
        $sql = "UPDATE `" . TB_PREF . "ksf_hrm_payroll_entries` SET ";
        $sets = array();
        foreach (array('payroll_id', 'element_id', 'amount', 'note') as $field) {
            if (isset($data[$field])) {
                $sets[] = "`$field` = " . db_escape($data[$field]);
            }
        }
        $sql .= implode(', ', $sets) . " WHERE `entry_id` = " . db_escape($entry_id);
    } else {
        $sql = "INSERT INTO `" . TB_PREF . "ksf_hrm_payroll_entries` SET ";
        $sets = array();
        foreach (array('payroll_id', 'element_id', 'amount', 'note') as $field) {
            if (isset($data[$field])) {
                $sets[] = "`$field` = " . db_escape($data[$field]);
            }
        }
        $sql .= implode(', ', $sets);
    }
    
    db_query($sql, "Failed to write payroll entry");
    return $entry_id ? $entry_id : db_insert_id();
}

function get_payroll_entries($payroll_id) {
    $sql = "SELECT pe.*, e.element_name, e.element_code, e.category
        FROM `" . TB_PREF . "ksf_hrm_payroll_entries` pe
        LEFT JOIN `" . TB_PREF . "hrm_pay_elements` e ON pe.element_id = e.element_id
        WHERE pe.`payroll_id` = " . db_escape($payroll_id) . "
        ORDER BY e.element_code";
    return db_query($sql, "Failed to get payroll entries");
}
?>
