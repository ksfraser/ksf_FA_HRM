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

// ---------------------------------------------------------------------------
// Employee CRUD
// ---------------------------------------------------------------------------

function insert_employee($data)
{
    $sql = "INSERT INTO " . TB_PREF . "fa_contacts_employment
            (person_id, employee_code, department_id, position_id, grade_id,
             reports_to_person_id, hire_date, probation_end_date, is_active)
            VALUES (" .
        (int)$data['person_id'] . ", " .
        db_escape($data['employee_code']) . ", " .
        (int)($data['department_id'] ?? 0) . ", " .
        (int)($data['position_id'] ?? 0) . ", " .
        (int)($data['grade_id'] ?? 0) . ", " .
        (int)($data['reports_to_person_id'] ?? 0) . ", " .
        db_escape($data['hire_date'] ?? null) . ", " .
        db_escape($data['probation_end_date'] ?? null) . ", " .
        (isset($data['is_active']) ? 1 : 0) . ")";
    db_query($sql, _("Could not insert employee"));
    return db_insert_id();
}

function update_employee($data)
{
    $sql = "UPDATE " . TB_PREF . "fa_contacts_employment SET
            person_id = " . (int)$data['person_id'] . ",
            employee_code = " . db_escape($data['employee_code']) . ",
            department_id = " . (int)($data['department_id'] ?? 0) . ",
            position_id = " . (int)($data['position_id'] ?? 0) . ",
            grade_id = " . (int)($data['grade_id'] ?? 0) . ",
            reports_to_person_id = " . (int)($data['reports_to_person_id'] ?? 0) . ",
            hire_date = " . db_escape($data['hire_date'] ?? null) . ",
            probation_end_date = " . db_escape($data['probation_end_date'] ?? null) . ",
            is_active = " . (isset($data['is_active']) ? 1 : 0) . "
            WHERE employment_id = " . (int)$data['employment_id'];
    db_query($sql, _("Could not update employee"));
}

// ---------------------------------------------------------------------------
// Department CRUD
// ---------------------------------------------------------------------------

function insert_department($data)
{
    $sql = "INSERT INTO " . TB_PREF . "fa_departments
            (department_code, department_name, description, is_active)
            VALUES (" .
        db_escape($data['department_code']) . ", " .
        db_escape($data['department_name']) . ", " .
        db_escape($data['description'] ?? '') . ", " .
        (isset($data['is_active']) ? 1 : 0) . ")";
    db_query($sql, _("Could not insert department"));
}

// ---------------------------------------------------------------------------
// Position CRUD
// ---------------------------------------------------------------------------

function insert_position($data)
{
    $sql = "INSERT INTO " . TB_PREF . "fa_positions
            (position_code, position_name, description, is_active)
            VALUES (" .
        db_escape($data['position_code']) . ", " .
        db_escape($data['position_name']) . ", " .
        db_escape($data['description'] ?? '') . ", " .
        (isset($data['is_active']) ? 1 : 0) . ")";
    db_query($sql, _("Could not insert position"));
}

function get_position($positionId)
{
    $sql = "SELECT * FROM " . TB_PREF . "fa_positions WHERE position_id = " . (int)$positionId;
    $result = db_query($sql);
    if ($result && db_num_rows($result)) {
        return db_fetch_assoc($result);
    }
    return null;
}

function update_position($data)
{
    $sql = "UPDATE " . TB_PREF . "fa_positions SET
            position_code = " . db_escape($data['position_code']) . ",
            position_name = " . db_escape($data['position_name']) . ",
            description = " . db_escape($data['description'] ?? '') . ",
            is_active = " . (isset($data['is_active']) ? 1 : 0) . "
            WHERE position_id = " . (int)$data['position_id'];
    db_query($sql, _("Could not update position"));
}

// ---------------------------------------------------------------------------
// Grade CRUD
// ---------------------------------------------------------------------------

function insert_grade($data)
{
    $sql = "INSERT INTO " . TB_PREF . "fa_grades
            (grade_code, grade_name, min_salary, max_salary, description, is_active)
            VALUES (" .
        db_escape($data['grade_code']) . ", " .
        db_escape($data['grade_name']) . ", " .
        (float)($data['min_salary'] ?? 0) . ", " .
        (float)($data['max_salary'] ?? 0) . ", " .
        db_escape($data['description'] ?? '') . ", " .
        (isset($data['is_active']) ? 1 : 0) . ")";
    db_query($sql, _("Could not insert grade"));
}

function get_grade($gradeId)
{
    $sql = "SELECT * FROM " . TB_PREF . "fa_grades WHERE grade_id = " . (int)$gradeId;
    $result = db_query($sql);
    if ($result && db_num_rows($result)) {
        return db_fetch_assoc($result);
    }
    return null;
}

function update_grade($data)
{
    $sql = "UPDATE " . TB_PREF . "fa_grades SET
            grade_code = " . db_escape($data['grade_code']) . ",
            grade_name = " . db_escape($data['grade_name']) . ",
            min_salary = " . (float)($data['min_salary'] ?? 0) . ",
            max_salary = " . (float)($data['max_salary'] ?? 0) . ",
            description = " . db_escape($data['description'] ?? '') . ",
            is_active = " . (isset($data['is_active']) ? 1 : 0) . "
            WHERE grade_id = " . (int)$data['grade_id'];
    db_query($sql, _("Could not update grade"));
}

// ---------------------------------------------------------------------------
// Benefit CRUD
// ---------------------------------------------------------------------------

function insert_benefit($data)
{
    $sql = "INSERT INTO " . TB_PREF . "ksf_hrm_benefits
            (benefit_code, benefit_name, benefit_type, employer_contribution, employee_contribution, gl_account_code, is_active)
            VALUES (" .
        db_escape($data['benefit_code']) . ", " .
        db_escape($data['benefit_name']) . ", " .
        db_escape($data['benefit_type'] ?? '') . ", " .
        (float)($data['employer_contribution'] ?? 0) . ", " .
        (float)($data['employee_contribution'] ?? 0) . ", " .
        db_escape($data['gl_account_code'] ?? '') . ", " .
        (isset($data['is_active']) ? 1 : 0) . ")";
    db_query($sql, _("Could not insert benefit"));
    return db_insert_id();
}

function get_leave_types()
{
    $sql = "SELECT * FROM " . TB_PREF . "ksf_hrm_leave_types ORDER BY type_name";
    return db_query($sql, _("Could not query leave types"));
}

function insert_leave_type($data)
{
    $sql = "INSERT INTO " . TB_PREF . "ksf_hrm_leave_types
            (type_code, type_name, default_days, is_paid, is_active)
            VALUES (" .
        db_escape($data['type_code']) . ", " .
        db_escape($data['type_name']) . ", " .
        (float)($data['default_days'] ?? 0) . ", " .
        (isset($data['is_paid']) ? 1 : 0) . ", " .
        (isset($data['is_active']) ? 1 : 0) . ")";
    db_query($sql, _("Could not insert leave type"));
    return db_insert_id();
}

function get_employment_statuses()
{
    $sql = "SELECT * FROM " . TB_PREF . "ksf_hrm_employment_status ORDER BY status_name";
    return db_query($sql, _("Could not query employment statuses"));
}

function get_departments()
{
    $sql = "SELECT * FROM " . TB_PREF . "fa_departments WHERE is_active = 1 ORDER BY department_name";
    return db_query($sql, _("Could not query departments"));
}

function get_positions_list()
{
    $sql = "SELECT * FROM " . TB_PREF . "fa_positions WHERE is_active = 1 ORDER BY position_name";
    return db_query($sql, _("Could not query positions"));
}

function get_grades_list()
{
    $sql = "SELECT * FROM " . TB_PREF . "fa_grades WHERE is_active = 1 ORDER BY grade_name";
    return db_query($sql, _("Could not query grades"));
}
