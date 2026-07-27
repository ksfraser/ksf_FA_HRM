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

// ---------------------------------------------------------------------------
// Employee queries
// ---------------------------------------------------------------------------

function get_employees()
{
    $sql = "SELECT e.*, d.department_name, d.department_code,
                   p.position_code, r.role_name, tm.team_name,
                   CONCAT(COALESCE(cp.firstname, ''), ' ', COALESCE(cp.lastname, '')) AS person_name
            FROM " . TB_PREF . "fa_contacts_employment e
            LEFT JOIN " . TB_PREF . "fa_departments d ON e.department_id = d.department_id
            LEFT JOIN " . TB_PREF . "fa_positions p ON e.position_id = p.position_id
            LEFT JOIN " . TB_PREF . "fa_roles r ON p.role_id = r.role_id
            LEFT JOIN " . TB_PREF . "fa_teams tm ON p.team_id = tm.team_id
            LEFT JOIN " . TB_PREF . "crm_persons cp ON e.person_id = cp.id
            ORDER BY e.employee_code ASC";

    return db_query($sql);
}

function get_employee($employmentId)
{
    $sql = "SELECT e.*, d.department_name, d.department_code,
                   p.position_code, r.role_name, tm.team_name,
                   CONCAT(COALESCE(cp.firstname, ''), ' ', COALESCE(cp.lastname, '')) AS person_name
            FROM " . TB_PREF . "fa_contacts_employment e
            LEFT JOIN " . TB_PREF . "fa_departments d ON e.department_id = d.department_id
            LEFT JOIN " . TB_PREF . "fa_positions p ON e.position_id = p.position_id
            LEFT JOIN " . TB_PREF . "fa_roles r ON p.role_id = r.role_id
            LEFT JOIN " . TB_PREF . "fa_teams tm ON p.team_id = tm.team_id
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
    return db_insert_id();
}

// ---------------------------------------------------------------------------
// Role Dictionary (global)
// ---------------------------------------------------------------------------

function get_role_dictionary()
{
    $sql = "SELECT * FROM " . TB_PREF . "fa_role_dictionary ORDER BY role_name";
    return db_query($sql);
}

// ---------------------------------------------------------------------------
// Department Roles (cloned from dictionary)
// ---------------------------------------------------------------------------

function get_roles_for_department($departmentId)
{
    $sql = "SELECT r.*, rd.role_name AS dict_name
            FROM " . TB_PREF . "fa_roles r
            LEFT JOIN " . TB_PREF . "fa_role_dictionary rd ON r.role_dict_id = rd.role_dict_id
            WHERE r.department_id = " . (int)$departmentId . "
            ORDER BY r.role_name";
    return db_query($sql);
}

function insert_role($data)
{
    $sql = "INSERT INTO " . TB_PREF . "fa_roles
            (department_id, role_dict_id, role_name, description, is_active)
            VALUES (" .
        (int)$data['department_id'] . ", " .
        (int)($data['role_dict_id'] ?? 0) . ", " .
        db_escape($data['role_name']) . ", " .
        db_escape($data['description'] ?? '') . ", " .
        (isset($data['is_active']) ? 1 : 0) . ")";
    db_query($sql, _("Could not insert role"));
    return db_insert_id();
}

// ---------------------------------------------------------------------------
// Teams (recursive, within department)
// ---------------------------------------------------------------------------

function get_teams_for_department($departmentId)
{
    $sql = "SELECT t.*,
                   COALESCE(parent.team_name, '') AS parent_team_name
            FROM " . TB_PREF . "fa_teams t
            LEFT JOIN " . TB_PREF . "fa_teams parent ON t.parent_team_id = parent.team_id
            WHERE t.department_id = " . (int)$departmentId . "
            ORDER BY parent.team_name, t.team_name";
    return db_query($sql);
}

function get_all_teams()
{
    $sql = "SELECT t.*, d.department_code, d.department_name,
                   COALESCE(parent.team_name, '') AS parent_team_name
            FROM " . TB_PREF . "fa_teams t
            LEFT JOIN " . TB_PREF . "fa_departments d ON t.department_id = d.department_id
            LEFT JOIN " . TB_PREF . "fa_teams parent ON t.parent_team_id = parent.team_id
            ORDER BY d.department_code, parent.team_name, t.team_name";
    return db_query($sql);
}

function insert_team($data)
{
    $parent = (int)($data['parent_team_id'] ?? 0);
    $sql = "INSERT INTO " . TB_PREF . "fa_teams
            (department_id, parent_team_id, team_code, team_name, description, is_active)
            VALUES (" .
        (int)$data['department_id'] . ", " .
        ($parent > 0 ? $parent : 'NULL') . ", " .
        db_escape($data['team_code']) . ", " .
        db_escape($data['team_name']) . ", " .
        db_escape($data['description'] ?? '') . ", " .
        (isset($data['is_active']) ? 1 : 0) . ")";
    db_query($sql, _("Could not insert team"));
    return db_insert_id();
}

function get_team($teamId)
{
    $sql = "SELECT * FROM " . TB_PREF . "fa_teams WHERE team_id = " . (int)$teamId;
    $result = db_query($sql);
    if ($result && db_num_rows($result)) {
        return db_fetch_assoc($result);
    }
    return null;
}

function update_team($data)
{
    $parent = (int)($data['parent_team_id'] ?? 0);
    $sql = "UPDATE " . TB_PREF . "fa_teams SET
            parent_team_id = " . ($parent > 0 ? $parent : 'NULL') . ",
            team_code = " . db_escape($data['team_code']) . ",
            team_name = " . db_escape($data['team_name']) . ",
            description = " . db_escape($data['description'] ?? '') . ",
            is_active = " . (isset($data['is_active']) ? 1 : 0) . "
            WHERE team_id = " . (int)$data['team_id'];
    db_query($sql, _("Could not update team"));
}

// ---------------------------------------------------------------------------
// Positions (DEPT-TEAM-###)
// ---------------------------------------------------------------------------

function generate_position_code($departmentId, $teamId)
{
    $dept = db_fetch_assoc(db_query(
        "SELECT department_code FROM " . TB_PREF . "fa_departments WHERE department_id = " . (int)$departmentId
    ));
    $team = db_fetch_assoc(db_query(
        "SELECT team_code FROM " . TB_PREF . "fa_teams WHERE team_id = " . (int)$teamId
    ));

    $deptCode = $dept ? strtoupper(substr($dept['department_code'], 0, 3)) : 'UNK';
    $teamCode = $team ? strtoupper(substr($team['team_code'], 0, 3)) : 'GEN';

    $sql = "SELECT MAX(position_number) AS max_num
            FROM " . TB_PREF . "fa_positions
            WHERE department_id = " . (int)$departmentId . "
            AND team_id = " . (int)$teamId;
    $result = db_fetch_assoc(db_query($sql));
    $next = ($result && $result['max_num']) ? (int)$result['max_num'] + 1 : 1;

    return $deptCode . '-' . $teamCode . '-' . str_pad($next, 3, '0', STR_PAD_LEFT);
}

function insert_position($data)
{
    $teamId = (int)($data['team_id'] ?? 0);
    if ($teamId <= 0) {
        $teamId = null;
    }

    $code = generate_position_code($data['department_id'], $teamId);

    $sql = "INSERT INTO " . TB_PREF . "fa_positions
            (position_code, department_id, team_id, role_id, position_number, description, is_active)
            VALUES (" .
        db_escape($code) . ", " .
        (int)$data['department_id'] . ", " .
        ($teamId ? (int)$teamId : 'NULL') . ", " .
        (int)$data['role_id'] . ", " .
        (int)($data['position_number'] ?? 1) . ", " .
        db_escape($data['description'] ?? '') . ", " .
        (isset($data['is_active']) ? 1 : 0) . ")";
    db_query($sql, _("Could not insert position"));
    return db_insert_id();
}

function get_position($positionId)
{
    $sql = "SELECT p.*, d.department_code, t.team_code, r.role_name
            FROM " . TB_PREF . "fa_positions p
            LEFT JOIN " . TB_PREF . "fa_departments d ON p.department_id = d.department_id
            LEFT JOIN " . TB_PREF . "fa_teams t ON p.team_id = t.team_id
            LEFT JOIN " . TB_PREF . "fa_roles r ON p.role_id = r.role_id
            WHERE p.position_id = " . (int)$positionId;
    $result = db_query($sql);
    if ($result && db_num_rows($result)) {
        return db_fetch_assoc($result);
    }
    return null;
}

function get_positions_for_department($departmentId)
{
    $sql = "SELECT p.*, t.team_code, t.team_name, r.role_name
            FROM " . TB_PREF . "fa_positions p
            LEFT JOIN " . TB_PREF . "fa_teams t ON p.team_id = t.team_id
            LEFT JOIN " . TB_PREF . "fa_roles r ON p.role_id = r.role_id
            WHERE p.department_id = " . (int)$departmentId . "
            ORDER BY p.position_code";
    return db_query($sql);
}

function update_position($data)
{
    $teamId = (int)($data['team_id'] ?? 0);
    $sql = "UPDATE " . TB_PREF . "fa_positions SET
            department_id = " . (int)$data['department_id'] . ",
            team_id = " . ($teamId > 0 ? $teamId : 'NULL') . ",
            role_id = " . (int)$data['role_id'] . ",
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

// ---------------------------------------------------------------------------
// Leave Types
// ---------------------------------------------------------------------------

function get_leave_types()
{
    $sql = "SELECT * FROM " . TB_PREF . "ksf_hrm_leave_types ORDER BY type_name";
    return db_query($sql);
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

// ---------------------------------------------------------------------------
// Lookups
// ---------------------------------------------------------------------------

function get_employment_statuses()
{
    $sql = "SELECT * FROM " . TB_PREF . "ksf_hrm_employment_status ORDER BY status_name";
    return db_query($sql);
}

function get_departments()
{
    $sql = "SELECT * FROM " . TB_PREF . "fa_departments WHERE is_active = 1 ORDER BY department_name";
    return db_query($sql);
}

function get_positions_list()
{
    $sql = "SELECT p.*, d.department_code, t.team_code, r.role_name
            FROM " . TB_PREF . "fa_positions p
            LEFT JOIN " . TB_PREF . "fa_departments d ON p.department_id = d.department_id
            LEFT JOIN " . TB_PREF . "fa_teams t ON p.team_id = t.team_id
            LEFT JOIN " . TB_PREF . "fa_roles r ON p.role_id = r.role_id
            WHERE p.is_active = 1
            ORDER BY p.position_code";
    return db_query($sql);
}

function get_grades_list()
{
    $sql = "SELECT * FROM " . TB_PREF . "fa_grades WHERE is_active = 1 ORDER BY grade_name";
    return db_query($sql);
}
