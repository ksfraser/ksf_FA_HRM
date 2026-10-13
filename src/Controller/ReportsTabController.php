<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Controller;

use ksfraser\FrontAccounting\HRM\Service\HrmReportService;

/**
 * ReportsTabController — HRM Reports tab.
 *
 * Replaces pages/reports.php. Every aggregate comes from HrmReportService; no
 * SQL in this class.
 *
 * This tab is READ-ONLY by design: a report produces figures, not records, so
 * there is no entry form and no per-row Edit/Delete. It therefore renders only
 * the filter bar and the summary table, with the report-definition notes in
 * place of the entry form — the same three-part shape the pages/ scripts used,
 * with the middle slot empty by design.
 *
 * The report's own columns vary by report (HrmReportService::run() returns
 * ['columns'=>string[], 'rows'=>array[]]), so the summary is rendered directly
 * rather than through MasterSummaryTable's metadata-driven columns. The TOTAL
 * row is emphasised, and money columns are formatted as amounts rather than
 * left as bare numbers.
 *
 * PHP 7.3 compatible.
 *
 * @package ksf_FA_HRM
 * @since   1.1.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §11 (tab controller SRP)
 * @BABOK Related: FR-HRM-001 (HRM reporting)
 */
class ReportsTabController extends FilteredTabController
{
    /** @var HrmReportService */
    private $reports;

    /** @var array<string, mixed> Currently resolved filter values. */
    private $reportKey = '';

    /** @var int */
    private $year = 0;

    /** @var int */
    private $departmentId = 0;

    /**
     * @param mixed                $context DI request state
     * @param array<string, mixed> $options
     *
     * @since 1.1.0
     */
    public function __construct($context = null, array $options = [])
    {
        parent::__construct($context, $options);
        $this->reports = new HrmReportService();
    }

    /** {@inheritDoc} */
    protected function getPkField(): string
    {
        return 'report_row';
    }

    /**
     * No metadata-driven field set: the columns belong to the selected report.
     *
     * @return array<string, mixed>
     *
     * @since 1.1.0
     */
    protected function getFieldMetadata(): array
    {
        return [
            'entity'     => 'report',
            'label'      => 'Report',
            'labelPlural' => 'Reports',
            'pk'         => 'report_row',
            'fields'     => [],
        ];
    }

    /**
     * Reports are read-only: filter bar and summary table only, notes instead
     * of an entry form, and no row actions.
     *
     * @return void
     *
     * @since 1.1.0
     */
    public function run(): void
    {
        $this->filters = $this->readFilters();
        $this->resolveFilters();

        // Reports are a pure read: there is no POST to route.
        echo $this->renderStart();
        $this->renderFilterSection();
        $this->renderReportTable();
        echo $this->renderEnd();
        $this->renderReportNotes();
    }

    /** {@inheritDoc} */
    protected function getFilterFields(): array
    {
        return [
            ['name' => 'filter_report', 'label' => $this->localise('Report:'),
                'type' => 'select', 'options' => $this->reports->reportTitles()],
            ['name' => 'filter_year', 'label' => $this->localise('Reporting Year:'),
                'type' => 'text', 'size' => 4, 'max' => 4],
            ['name' => 'filter_department_id', 'label' => $this->localise('Department:'),
                'type' => 'select', 'options' => $this->reports->departmentOptions(),
                'spec_option' => $this->localise('All departments')],
        ];
    }

    /** {@inheritDoc} */
    protected function normaliseFilters(array $input): array
    {
        return [
            'report'         => (string) ($input['filter_report'] ?? ''),
            'year'           => (int) ($input['filter_year'] ?? 0),
            'department_id'  => (int) ($input['filter_department_id'] ?? 0),
        ];
    }

    /**
     * Clamp the filters into a runnable report selection.
     *
     * @return void
     *
     * @since 1.1.0
     */
    private function resolveFilters(): void
    {
        $titles = $this->reports->reportTitles();

        $key = (string) ($this->filters['report'] ?? '');
        if (!isset($titles[$key])) {
            $keys = array_keys($titles);
            $key = $keys[0] ?? 'headcount';
        }
        $this->reportKey = $key;

        $year = (int) ($this->filters['year'] ?? 0);
        $this->year = ($year < 2000 || $year > 2100) ? (int) date('Y') : $year;

        $this->departmentId = (int) ($this->filters['department_id'] ?? 0);
    }

    /**
     * Run the selected report.
     *
     * @return array{columns: string[], rows: array<int, array<string, mixed>>}
     *
     * @since 1.1.0
     */
    private function report(): array
    {
        return $this->reports->run($this->reportKey, $this->year, $this->departmentId);
    }

    // ─── Read-only data-access contract ───────────────────────────────
    //
    // AbstractTabController requires the full CRUD set. A report stores
    // nothing, so these are deliberately inert rather than inherited-throwing:
    // the tab never calls them, and run() short-circuits before handlePost().

    /**
     * @param int $page
     * @param int $perPage
     *
     * @return array<int, array<string, mixed>> Always empty — see
     *                                             renderReportTable().
     *
     * @since 1.1.0
     */
    protected function listRows(int $page, int $perPage): array
    {
        return [];
    }

    /**
     * @return int Always 0.
     *
     * @since 1.1.0
     */
    protected function countRows(): int
    {
        return 0;
    }

    /**
     * @param string $pk
     *
     * @return array<string, mixed>|null Always null.
     *
     * @since 1.1.0
     */
    protected function findRecord(string $pk): ?array
    {
        return null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return int Always 0.
     *
     * @since 1.1.0
     */
    protected function createRecord(array $data)
    {
        return 0;
    }

    /**
     * @param string              $pk
     * @param array<string, mixed> $data
     *
     * @return void
     *
     * @since 1.1.0
     */
    protected function updateRecord(string $pk, array $data): void
    {
    }

    /**
     * @param string $pk
     *
     * @return void
     *
     * @since 1.1.0
     */
    protected function deleteRecord(string $pk): void
    {
    }

    /**
     * Render the report's summary table with its own columns.
     *
     * @return void
     *
     * @since 1.1.0
     */
    private function renderReportTable(): void
    {
        if (!function_exists('start_table')) {
            return;
        }

        $report = $this->report();

        start_table(TABLESTYLE, 'width=95%');
        table_section_title($this->reports->reportTitle($this->reportKey));
        table_header($report['columns']);

        foreach ($report['rows'] as $row) {
            $is_total = isset($row['employee']) && $row['employee'] === 'TOTAL';
            start_row($is_total ? "class='currentfg'" : '');

            $keys = array_keys($row);
            foreach ($report['columns'] as $index => $column) {
                $field = isset($keys[$index]) ? $keys[$index] : '';
                $value = $field !== '' ? $row[$field] : '';
                $this->renderCell((string) $column, $value, $index, $is_total);
            }

            end_row();
        }

        if (count($report['rows']) === 0) {
            start_row();
            label_cell($this->localise('This report has no rows for the selected filters.'),
                'colspan=' . count($report['columns']));
            end_row();
        }

        end_table(1);
    }

    /**
     * Emit one report cell: money columns as amounts, day/head counts as
     * quantities, everything else as a label.
     *
     * @param string $column  Column heading
     * @param mixed  $value   Cell value
     * @param int    $index   Column position (0 = leading label column)
     * @param bool   $isTotal Whether this is the emphasised total row
     *
     * @return void
     *
     * @since 1.1.0
     */
    private function renderCell(string $column, $value, int $index, bool $isTotal): void
    {
        // Money columns are the numeric ones after the leading label columns.
        if ($index > 0 && is_numeric($value)) {
            if (in_array($column, ['Gross', 'Deductions', 'Net'], true)) {
                amount_cell((float) $value, $isTotal);
            } else {
                qty_cell($value, $isTotal, 2);
            }
            return;
        }

        label_cell($value === null ? '' : (string) $value,
            $isTotal ? 'class=currentfg' : '');
    }

    /**
     * Report-definition notes, in place of the entry form.
     *
     * @return void
     *
     * @since 1.1.0
     */
    private function renderReportNotes(): void
    {
        if (!function_exists('start_table')) {
            return;
        }

        start_table(TABLESTYLE2, 'width=95%');
        table_section_title($this->localise('About These Reports'));

        // [label, definition]
        $notes = [
            [$this->localise('Employee Headcount:'),
             $this->localise('Counts active CRM persons, optionally narrowed to one department.')],
            [$this->localise('Department Summary:'),
             $this->localise('Active departments with the number of active employees attached to each.')],
            [$this->localise('Payroll Summary:'),
             $this->localise('Per-employee totals over payroll runs whose period starts inside the '
                 . 'selected year, with a grand total row. Only run headers are counted; the '
                 . 'individual earnings and deduction lines are not reconciled here.')],
            [$this->localise('Benefits Summary:'),
             $this->localise('Active benefit catalogue entries with the number of employee '
                 . 'enrolments recorded against each.')],
            [$this->localise('Leave Balance Report:'),
             $this->localise('Per-employee leave allocation for the selected year: allocated, '
                 . 'carried over, used, and the remaining balance.')],
        ];

        foreach ($notes as $note) {
            label_row($note[0], $note[1]);
        }

        end_table(1);

        if (function_exists('display_note')) {
            display_note($this->localise('Leave type names are resolved from the leave type '
                . 'master when the module that owns it is installed; on a standalone '
                . 'ksf_FA_HRM install the numeric type id is shown instead.'), 0);
        }
    }
}