<?php
/**
 * Employee database helpers for HRM module.
 *
 * @package ksf_FA_HRM
 * @since 1.0.0
 */

if (!defined('TB_PREF')) {
    define('TB_PREF', '0_');
}

/**
 * Get all employees with department and position names.
 *
 * @return result|false DB result set
 */
function get_employees()
{
    $sql = "SELECT e.*, d.department_name, p.position_name,
                   CONCAT(COALESCE(cp.firstname, ''), ' ', COALESCE(cp.lastname, '')) AS person_name
            FROM " . TB_PREF . "fa_contacts_employment e
            LEFT JOIN " . TB_PREF . "fa_departments d ON e.department_id = d.department_id
            LEFT JOIN " . TB_PREF . "fa_positions p ON e.position_id = p.position_id
            LEFT JOIN " . TB_PREF . "crm_persons cp ON e.person_id = cp.id
            ORDER BY e.employee_code ASC";

    $result = db_query($sql);
    return $result;
}

/**
 * Get a single employee by employment_id.
 *
 * @param int $employmentId
 * @return array|null
 */
function get_employee($employmentId)
{
    $sql = "SELECT e.*, d.department_name, p.position_name,
                   CONCAT(COALESCE(cp.firstname, ''), ' ', COALESCE(cp.lastname, '')) AS person_name
            FROM " . TB_PREF . "fa_contacts_employment e
            LEFT JOIN " . TB_PREF . "fa_departments d ON e.department_id = d.department_id
            LEFT JOIN " . TB_PREF . "fa_positions p ON e.position_id = p.position_id
            LEFT JOIN " . TB_PREF . "crm_persons cp ON e.person_id = cp.id
            WHERE e.employment_id = " . (int)$employmentId;

    $result = db_query($sql);
    if ($result && db_num_rows($result)) {
        return db_fetch_assoc($result);
    }
    return null;
}
