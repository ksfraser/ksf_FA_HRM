<?php

declare(strict_types=1);

/**
 * Employee import - FA-native.
 *
 * WHY THIS FILE EXISTS
 * --------------------
 * The employee import used to upsert a flat row into `0_ksf_employee` (and
 * `0_ksf_employees` in the standalone variant). Neither table is created by
 * any schema in the estate, so every imported row went nowhere and the
 * import reported success.
 *
 * A flat employee row is also not the estate's employee model. An employee is
 * an FA person (FA core's `0_crm_persons`) plus a row in HRM's own employment
 * table (`0_hrm_contacts_employment`). This module therefore:
 *
 *   - creates or updates the PERSON with FA's own native functions
 *     (`add_crm_person()` / `update_crm_person()`), never with raw SQL
 *     against an FA core table, and
 *   - creates or updates the EMPLOYMENT row in HRM's own table, keyed by
 *     `employee_code` = the CSV's `emp_no`.
 *
 * The contact CATEGORY ids of an existing person are read back and passed to
 * `update_crm_person()`, because that native function replaces the person's
 * contact relations wholesale.
 *
 * @package ksfraser\FrontAccounting\HRM
 */

if (!function_exists('hrm_employee_import_native_loaded')) {
    /**
     * Whether FA's native CRM functions are available.
     *
     * `includes/db/crm_contacts_db.inc` is NOT auto-loaded by
     * `includes/main.inc`; a module calling those functions must include it
     * itself. The file is a pure function-definition file, so a guarded
     * include_once is safe.
     *
     * @return bool
     */
    function hrm_employee_import_native_loaded(): bool
    {
        if (function_exists('add_crm_person')) {
            return true;
        }

        $pathToRoot = isset($GLOBALS['path_to_root']) ? (string) $GLOBALS['path_to_root'] : '';
        if ($pathToRoot === '') {
            return false;
        }

        $file = $pathToRoot . '/includes/db/crm_contacts_db.inc';
        if (!is_file($file)) {
            return false;
        }

        include_once $file;

        return function_exists('add_crm_person');
    }
}

if (!function_exists('hrm_employee_import_category_ids')) {
    /**
     * The contact category ids of an existing person.
     *
     * `update_crm_person()` replaces the person's contact relations, so the
     * categories have to be read back and handed to it unchanged.
     *
     * @param int $personId Person id
     *
     * @return int[]
     */
    function hrm_employee_import_category_ids(int $personId): array
    {
        if (!function_exists('get_person_contacts')) {
            return array();
        }

        $ids = array();
        foreach ((array) get_person_contacts($personId) as $row) {
            $id = is_array($row) ? ($row['id'] ?? null) : $row;
            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }
}

if (!function_exists('hrm_import_employee_row')) {
    /**
     * Import one employee row.
     *
     * @param array $row The CSV row: emp_no, first_name, last_name, email,
     *                    phone, department, position, location, joined_date,
     *                    salary, hourly_rate
     *
     * @return bool True when the row was imported
     */
    function hrm_import_employee_row(array $row): bool
    {
        if (!hrm_employee_import_native_loaded()) {
            return false;
        }

        $employeeCode = trim((string) ($row['emp_no'] ?? ''));
        if ($employeeCode === '') {
            return false;
        }

        $firstName = (string) ($row['first_name'] ?? '');
        $lastName  = (string) ($row['last_name'] ?? '');
        $email     = (string) ($row['email'] ?? '');
        $phone     = (string) ($row['phone'] ?? '');
        $address   = (string) ($row['location'] ?? '');
        $hireDate  = (string) ($row['joined_date'] ?? '');
        $salary    = (string) ($row['salary'] ?? '');

        // The employee directory is HRM's own table, keyed by employee_code.
        $existing = db_fetch_assoc(db_query(
            "SELECT employment_id, person_id FROM " . TB_PREF . "hrm_contacts_employment"
            . " WHERE employee_code = " . db_escape($employeeCode)
        ));

        if ($existing) {
            $personId = (int) $existing['person_id'];

            update_crm_person(
                $personId,
                $employeeCode,
                $firstName,
                $lastName,
                $address,
                $phone,
                '',
                '',
                $email,
                '',
                '',
                hrm_employee_import_category_ids($personId)
            );

            db_query(
                "UPDATE " . TB_PREF . "hrm_contacts_employment SET"
                . " hire_date = " . db_escape($hireDate !== '' ? $hireDate : null) . ","
                . " salary_amount = " . db_escape($salary !== '' ? $salary : 0) . ","
                . " is_active = 1"
                . " WHERE employment_id = " . (int) $existing['employment_id']
            );

            return true;
        }

        $personId = add_crm_person(
            $employeeCode,
            $firstName,
            $lastName,
            $address,
            $phone,
            '',
            '',
            $email,
            '',
            ''
        );

        if (!$personId) {
            return false;
        }

        db_query(
            "INSERT INTO " . TB_PREF . "hrm_contacts_employment"
            . " (person_id, employee_code, hire_date, salary_amount, is_active)"
            . " VALUES (" . (int) $personId . ","
            . db_escape($employeeCode) . ","
            . db_escape($hireDate !== '' ? $hireDate : null) . ","
            . db_escape($salary !== '' ? $salary : 0) . ","
            . "1)"
        );

        return true;
    }
}
