<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Service;

use ksfraser\FrontAccounting\HRM\Repository\DepartmentRepository;
use ksfraser\FrontAccounting\HRM\Repository\LookupRepository;

/**
 * HrmReportService — the aggregates behind pages/reports.php.
 *
 * Each report is a method returning a plain column-list + row-array pair, so
 * the page can hand it straight to table_header()/start_row() without knowing
 * anything about the underlying HRM tables.
 *
 * Reports are read-only: no report method writes.
 *
 * PHP 7.3 compatible.
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §10 (page -> service -> repository)
 * @BABOK Related: FR-HRM-001 (HRM reporting)
 */
class HrmReportService
{
    /** @var LookupRepository */
    private $lookups;

    /** @var DepartmentRepository */
    private $departments;

    /**
     * @param LookupRepository|null    $lookups     DI seam for tests
     * @param DepartmentRepository|null $departments DI seam for tests
     *
     * @since 1.0.0
     */
    public function __construct(?LookupRepository $lookups = null, ?DepartmentRepository $departments = null)
    {
        $this->lookups = $lookups ?? new LookupRepository();
        $this->departments = $departments ?? new DepartmentRepository();
    }

    /**
     * Report catalogue: key => title, in display order.
     *
     * @return array<string,string>
     *
     * @since 1.0.0
     */
    public function reportTitles(): array
    {
        return [
            'headcount' => 'Employee Headcount',
            'departments' => 'Department Summary',
            'payroll' => 'Payroll Summary',
            'benefits' => 'Benefits Summary',
            'leave' => 'Leave Balance Report',
        ];
    }

    /**
     * Resolve a report key to its title, defaulting to the first entry.
     *
     * @param string $key
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function reportTitle(string $key): string
    {
        $titles = $this->reportTitles();
        return isset($titles[$key]) ? $titles[$key] : reset($titles);
    }

    /**
     * Run one report.
     *
     * @param string $key     report key
     * @param int    $year    reporting year (leave/payroll reports only)
     * @param int    $deptId  department filter, 0 = all
     *
     * @return array ['columns'=>string[], 'rows'=>array[]]
     *
     * @since 1.0.0
     */
    public function run(string $key, int $year, int $deptId = 0): array
    {
        switch ($key) {
            case 'departments':
                return $this->departmentSummary();
            case 'payroll':
                return $this->payrollSummary($year);
            case 'benefits':
                return $this->benefitsSummary();
            case 'leave':
                return $this->leaveSummary($year);
            case 'headcount':
            default:
                return $this->headcount($deptId);
        }
    }

    /**
     * Active persons, optionally narrowed to one department.
     *
     * Headcount follows 0_crm_persons.inactive rather than a separate HRM
     * employment flag, because the employment detail table
     * (0_hrm_contacts_employment) is maintained by the CRM contact sync and is
     * not guaranteed to have a row per person.
     *
     * @param int $deptId department_id, 0 = all
     *
     * @return array
     *
     * @since 1.0.0
     */
    protected function headcount(int $deptId): array
    {
        $where = " WHERE p.inactive = 0";
        if ($deptId > 0) {
            $where .= " AND ce.department_id = " . (int) $deptId;
        }
        $sql = "SELECT p.name, ce.employee_code, ce.department_id, ce.hire_date
            FROM " . TB_PREF . "crm_persons p
            LEFT JOIN " . TB_PREF . "hrm_contacts_employment ce ON ce.person_id = p.id"
            . $where
            . " ORDER BY p.name";

        return [
            'columns' => ['Employee', 'Code', 'Department', 'Hired'],
            'rows' => $this->rows($sql),
        ];
    }

    /**
     * Active departments with their headcount.
     *
     * @return array
     *
     * @since 1.0.0
     */
    protected function departmentSummary(): array
    {
        $sql = "SELECT d.department_code, d.department_name, COUNT(p.id) AS headcount
            FROM " . TB_PREF . "hrm_departments d
            LEFT JOIN " . TB_PREF . "hrm_contacts_employment ce ON ce.department_id = d.department_id
            LEFT JOIN " . TB_PREF . "crm_persons p ON p.id = ce.person_id AND p.inactive = 0
            WHERE d.is_active = 1
            GROUP BY d.department_code, d.department_name
            ORDER BY d.department_code";

        return [
            'columns' => ['Code', 'Department', 'Headcount'],
            'rows' => $this->rows($sql),
        ];
    }

    /**
     * Per-employee payroll totals for the year, with a grand total row.
     *
     * @param int $year
     *
     * @return array
     *
     * @since 1.0.0
     */
    protected function payrollSummary(int $year): array
    {
        $sql = "SELECT COALESCE(c.name, '(unknown)') AS employee,
                COUNT(p.payroll_id) AS runs,
                COALESCE(SUM(p.gross_pay), 0) AS gross,
                COALESCE(SUM(p.total_deductions), 0) AS deductions,
                COALESCE(SUM(p.net_pay), 0) AS net
            FROM " . TB_PREF . "hrm_payroll p
            LEFT JOIN " . TB_PREF . "crm_persons c ON c.id = p.person_id
            WHERE p.pay_period_start >= " . $this->q($year . '-01-01') . "
              AND p.pay_period_start <= " . $this->q($year . '-12-31') . "
            GROUP BY c.name
            ORDER BY employee";

        $rows = $this->rows($sql);
        $totals = [0, 0.0, 0.0, 0.0];
        foreach ($rows as $row) {
            $totals[0] += (int) $row['runs'];
            $totals[1] += (float) $row['gross'];
            $totals[2] += (float) $row['deductions'];
            $totals[3] += (float) $row['net'];
        }
        $rows[] = [
            'employee' => 'TOTAL',
            'runs' => $totals[0],
            'gross' => $totals[1],
            'deductions' => $totals[2],
            'net' => $totals[3],
        ];

        return [
            'columns' => ['Employee', 'Runs', 'Gross', 'Deductions', 'Net'],
            'rows' => $rows,
        ];
    }

    /**
     * Benefit catalogue with the number of employees enrolled.
     *
     * @return array
     *
     * @since 1.0.0
     */
    protected function benefitsSummary(): array
    {
        $sql = "SELECT b.benefit_code, b.benefit_name, b.benefit_type,
                COUNT(eb.id) AS enrolled
            FROM " . TB_PREF . "hrm_benefits b
            LEFT JOIN " . TB_PREF . "hrm_employee_benefits eb ON eb.benefit_id = b.benefit_id
            WHERE b.is_active = 1
            GROUP BY b.benefit_id, b.benefit_code, b.benefit_name, b.benefit_type
            ORDER BY b.benefit_code";

        return [
            'columns' => ['Code', 'Benefit', 'Type', 'Enrolled'],
            'rows' => $this->rows($sql),
        ];
    }

    /**
     * Leave entitlement per employee for the year.
     *
     * @param int $year
     *
     * @return array
     *
     * @since 1.0.0
     */
    protected function leaveSummary(int $year): array
    {
        $sql = "SELECT COALESCE(c.name, '(unknown)') AS employee,
                b.leave_type_id,
                b.allocated_days, b.carried_over, b.used_days,
                (b.allocated_days + b.carried_over - b.used_days) AS balance
            FROM " . TB_PREF . "hrm_leave_balances b
            LEFT JOIN " . TB_PREF . "crm_persons c ON c.id = b.person_id
            WHERE b.`year` = " . (int) $year . "
            ORDER BY employee, b.leave_type_id";

        return [
            'columns' => ['Employee', 'Type', 'Allocated', 'Carried', 'Used', 'Balance'],
            'rows' => $this->rows($sql),
        ];
    }

    /**
     * Run a query and collect rows, suppressing the FA error box on failure.
     *
     * A report must never take the page down: a missing table yields an empty
     * result set rather than a fatal.
     *
     * @param string $sql
     *
     * @return array
     *
     * @since 1.0.0
     */
    protected function rows(string $sql): array
    {
        $result = @db_query($sql, false);
        if (!$result) {
            return [];
        }
        $out = [];
        while ($row = db_fetch_assoc($result)) {
            $out[] = $row;
        }
        return $out;
    }

    /**
     * Escape a scalar for inlining into report SQL.
     *
     * db_escape() follows FA's convention and returns a PRE-QUOTED literal,
     * so callers inline the result as-is.
     *
     * @param string $value
     *
     * @return string
     *
     * @since 1.0.0
     */
protected function q(string $value): string
    {
        return db_escape($value);
    }

    /**
     * Department options for the report filter.
     *
     * @return array<int,string>
     *
     * @since 1.0.0
     */
    public function departmentOptions(): array
    {
        $out = [];
        foreach ($this->departments->findActive() as $dept) {
            $out[(int) $dept->getDepartmentId()] = (string) $dept->getDepartmentName();
        }
        return $out;
    }
}