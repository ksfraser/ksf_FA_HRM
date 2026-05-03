<?php
/**
 * DB functions for ksf_hrm_employee_benefits table
 * Links employees (contacts) to benefits
 */

function write_employee_benefit($id, $data) {
    if ($id) {
        $sql = "UPDATE `" . TB_PREF . "ksf_hrm_employee_benefits` SET ";
        $sets = array();
        foreach (array('person_id', 'benefit_id', 'effective_date', 'end_date', 'custom_employer_rate', 'custom_employee_rate', 'notes') as $field) {
            if (isset($data[$field])) {
                $sets[] = "`$field` = " . db_escape($data[$field]);
            }
        }
        $sql .= implode(', ', $sets) . " WHERE `id` = " . db_escape($id);
    } else {
        $sql = "INSERT INTO `" . TB_PREF . "ksf_hrm_employee_benefits` SET ";
        $sets = array();
        foreach (array('person_id', 'benefit_id', 'effective_date', 'end_date', 'custom_employer_rate', 'custom_employee_rate', 'notes') as $field) {
            if (isset($data[$field])) {
                $sets[] = "`$field` = " . db_escape($data[$field]);
            }
        }
        $sql .= implode(', ', $sets);
    }
    
    db_query($sql, "Failed to write employee benefit");
    return $id ? $id : db_insert_id();
}

function get_employee_benefit($id) {
    $sql = "SELECT eb.*, b.benefit_name, b.benefit_code, p.name as employee_name
        FROM `" . TB_PREF . "ksf_hrm_employee_benefits` eb
        LEFT JOIN `" . TB_PREF . "ksf_hrm_benefits` b ON eb.benefit_id = b.benefit_id
        LEFT JOIN `" . TB_PREF . "crm_persons` p ON eb.person_id = p.id
        WHERE eb.`id` = " . db_escape($id);
    $result = db_query($sql, "Failed to get employee benefit");
    return db_fetch($result);
}

function get_employee_benefits($person_id) {
    $sql = "SELECT eb.*, b.benefit_name, b.benefit_type, b.is_mandatory
        FROM `" . TB_PREF . "ksf_hrm_employee_benefits` eb
        LEFT JOIN `" . TB_PREF . "ksf_hrm_benefits` b ON eb.benefit_id = b.benefit_id
        WHERE eb.`person_id` = " . db_escape($person_id) . "
        AND (eb.`end_date` IS NULL OR eb.`end_date` >= CURDATE())
        ORDER BY b.benefit_name";
    return db_query($sql, "Failed to get employee benefits");
}

function delete_employee_benefit($id) {
    $sql = "DELETE FROM `" . TB_PREF . "ksf_hrm_employee_benefits` WHERE `id` = " . db_escape($id);
    db_query($sql, "Failed to delete employee benefit");
}
?>
