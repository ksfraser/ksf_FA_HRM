<?php
/**
 * DB functions for ksf_hrm_benefits table
 */

function write_hrm_benefit($benefit_id, $data) {
    if ($benefit_id) {
        $sql = "UPDATE `" . TB_PREF . "ksf_hrm_benefits` SET ";
        $sets = array();
        foreach (array('benefit_name', 'benefit_code', 'benefit_type', 'employer_rate', 'employee_rate', 'fixed_amount', 'calculation_period', 'is_percentage_based', 'gl_code_expense', 'gl_code_liability', 'provider', 'description', 'is_active', 'is_mandatory', 'is_tax_deductible') as $field) {
            if (isset($data[$field])) {
                $sets[] = "`$field` = " . db_escape($data[$field]);
            }
        }
        $sql .= implode(', ', $sets) . " WHERE `benefit_id` = " . db_escape($benefit_id);
    } else {
        $sql = "INSERT INTO `" . TB_PREF . "ksf_hrm_benefits` SET ";
        $sets = array();
        foreach (array('benefit_name', 'benefit_code', 'benefit_type', 'employer_rate', 'employee_rate', 'fixed_amount', 'calculation_period', 'is_percentage_based', 'gl_code_expense', 'gl_code_liability', 'provider', 'description', 'is_active', 'is_mandatory', 'is_tax_deductible') as $field) {
            if (isset($data[$field])) {
                $sets[] = "`$field` = " . db_escape($data[$field]);
            }
        }
        $sql .= implode(', ', $sets);
    }
    
    db_query($sql, "Failed to write benefit");
    return $benefit_id ? $benefit_id : db_insert_id();
}

function get_hrm_benefit($benefit_id) {
    $sql = "SELECT * FROM `" . TB_PREF . "ksf_hrm_benefits` WHERE `benefit_id` = " . db_escape($benefit_id);
    $result = db_query($sql, "Failed to get benefit");
    return db_fetch($result);
}

function get_all_hrm_benefits($active_only = true) {
    $sql = "SELECT * FROM `" . TB_PREF . "ksf_hrm_benefits`";
    if ($active_only) {
        $sql .= " WHERE `is_active` = 1";
    }
    $sql .= " ORDER BY `benefit_name`";
    return db_query($sql, "Failed to get benefits");
}

function delete_hrm_benefit($benefit_id) {
    $sql = "UPDATE `" . TB_PREF . "ksf_hrm_benefits` SET `is_active` = 0 WHERE `benefit_id` = " . db_escape($benefit_id);
    db_query($sql, "Failed to delete benefit");
}
?>
