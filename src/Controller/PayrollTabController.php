<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Controller;

use ksfraser\FrontAccounting\HRM\Service\PayrollService;

/**
 * PayrollTabController — HRM Payroll tab.
 *
 * Replaces pages/payroll.php. Reads go through PayrollService::search();
 * writes go through createRun() / updateRun() / delete(). No SQL here.
 *
 * Net pay is never trusted from the browser: collectFormValues() recomputes it
 * as gross less deductions and the entry form renders it as a read-only label,
 * so the stored figure and the on-screen figure cannot drift apart.
 *
 * The status combo is seeded with the canonical statuses when no run exists
 * yet, so the first entry is not blocked by an empty dropdown.
 *
 * PHP 7.3 compatible.
 *
 * @package ksf_FA_HRM
 * @since   1.1.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §11 (tab controller SRP)
 * @BABOK Related: FR-HRM-001-002 (payroll)
 */
class PayrollTabController extends FilteredTabController
{
    /** @var PayrollService */
    private $service;

    /**
     * @param mixed                $context DI request state
     * @param array<string, mixed> $options
     *
     * @since 1.1.0
     */
    public function __construct($context = null, array $options = [])
    {
        parent::__construct($context, $options);
        $this->service = new PayrollService();
    }

    /** {@inheritDoc} */
    protected function getPkField(): string
    {
        return 'payroll_id';
    }

    /** {@inheritDoc} */
    protected function getFieldMetadata(): array
    {
        return [
            'entity'      => 'payroll',
            'table'       => TB_PREF . 'hrm_payroll',
            'label'       => 'Payroll Run',
            'labelPlural' => 'Payroll Runs',
            'hookPrefix'  => 'payroll',
            'pk'          => 'payroll_id',
            'fields'      => [
                'payroll_id' => [
                    'label' => 'ID', 'type' => 'text',
                    'showInForm' => false, 'showInTable' => false,
                ],
                'employee_name' => [
                    'label' => 'Employee', 'type' => 'text',
                    'showInTable' => true, 'showInForm' => false,
                ],
                'person_id' => [
                    'label' => 'Employee', 'type' => 'select',
                    'showInTable' => false, 'showInForm' => true,
                ],
                'pay_period_start' => [
                    'label' => 'Period Start', 'type' => 'date',
                    'showInTable' => true, 'showInForm' => true,
                ],
                'pay_period_end' => [
                    'label' => 'Period End', 'type' => 'date',
                    'showInTable' => true, 'showInForm' => true,
                ],
                'pay_date' => [
                    'label' => 'Pay Date', 'type' => 'date',
                    'showInTable' => true, 'showInForm' => true,
                ],
                'gross_pay' => [
                    'label' => 'Gross Pay', 'type' => 'number', 'dec' => 2,
                    'showInTable' => true, 'showInForm' => true,
                ],
                'total_deductions' => [
                    'label' => 'Deductions', 'type' => 'number', 'dec' => 2,
                    'showInTable' => true, 'showInForm' => true,
                ],
                // Read-only: recomputed on submit from gross - deductions.
                'net_pay' => [
                    'label' => 'Net Pay', 'type' => 'label', 'format' => 'price',
                    'showInTable' => true, 'showInForm' => true,
                ],
                'status' => [
                    'label' => 'Status', 'type' => 'select',
                    'default' => 'Draft',
                    'showInTable' => true, 'showInForm' => true,
                ],
                'is_gl_posted' => [
                    'label' => 'GL', 'type' => 'text',
                    'showInTable' => true, 'showInForm' => false,
                ],
            ],
            'fk_ddls'   => [],
            'ddlHooks'  => [],
            'tableSettings' => [],
        ];
    }

    /**
     * Employee options for the form; status options for both filter and form.
     *
     * @return array<string, array<string, string>>
     *
     * @since 1.1.0
     */
    protected function fkOptions(): array
    {
        return [
            'person_id' => $this->service->personOptions(),
            'status'    => $this->statusOptions(),
        ];
    }

    /**
     * Statuses actually in use, falling back to the canonical set so the combo
     * is usable before the first run exists.
     *
     * @return array<string, string>
     *
     * @since 1.1.0
     */
    private function statusOptions(): array
    {
        $statuses = $this->service->statusOptions();
        if (count($statuses) === 0) {
            $statuses = [
                'Draft'    => $this->localise('Draft'),
                'Approved' => $this->localise('Approved'),
                'Paid'     => $this->localise('Paid'),
            ];
        }
        return $statuses;
    }

    /** {@inheritDoc} */
    protected function getFilterFields(): array
    {
        $options = $this->fkOptions();

        return [
            ['name' => 'filter_person_id', 'label' => $this->localise('Employee:'),
                'type' => 'select', 'options' => $options['person_id'],
                'spec_option' => $this->localise('All employees')],
            ['name' => 'filter_status', 'label' => $this->localise('Status:'),
                'type' => 'select', 'options' => $options['status'],
                'spec_option' => $this->localise('All statuses')],
            // Deliberately text_row, not a date picker: an empty date field is
            // how "no bound" is expressed, and FA's date_cells() would
            // substitute today, turning "no filter" into a filter that hides
            // every run outside the current period.
            ['name' => 'filter_from_date', 'label' => $this->localise('Period From:'),
                'type' => 'text', 'size' => 10, 'max' => 10],
            ['name' => 'filter_to_date', 'label' => $this->localise('Period To:'),
                'type' => 'text', 'size' => 10, 'max' => 10],
        ];
    }

    /** {@inheritDoc} */
    protected function normaliseFilters(array $input): array
    {
        return [
            'person_id'  => (int) ($input['filter_person_id'] ?? 0),
            'status'     => (string) ($input['filter_status'] ?? ''),
            'from_date'  => (string) ($input['filter_from_date'] ?? ''),
            'to_date'    => (string) ($input['filter_to_date'] ?? ''),
        ];
    }

    /**
     * The rows shown in the summary table, keyed by the metadata column keys.
     *
     * @param int $page
     * @param int $perPage
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 1.1.0
     */
    private function summaryRows(int $page, int $perPage): array
    {
        $rows = [];
        $offset = ($page - 1) * $perPage;
        foreach (array_slice($this->service->search($this->filters), $offset, $perPage) as $payroll) {
            $rows[] = [
                'payroll_id'         => $payroll->getPayrollId(),
                'employee_name'      => !empty($payroll->getEmployeeName())
                    ? $payroll->getEmployeeName() : $this->localise('(unknown)'),
                'pay_period_start'   => $this->formatDate($payroll->getPayPeriodStart()),
                'pay_period_end'     => $this->formatDate($payroll->getPayPeriodEnd()),
                'pay_date'           => $this->formatDate($payroll->getPayDate()),
                'gross_pay'          => $this->formatMoney($payroll->getGrossPay()),
                'total_deductions'   => $this->formatMoney($payroll->getTotalDeductions()),
                'net_pay'            => $this->formatMoney($payroll->getNetPay()),
                'status'             => $payroll->getStatus(),
                'is_gl_posted'       => $payroll->isGlPosted()
                    ? $this->localise('Posted') : $this->localise('No'),
            ];
        }
        return $rows;
    }

    /** {@inheritDoc} */
    protected function listRows(int $page, int $perPage): array
    {
        return $this->summaryRows($page, $perPage);
    }

    /** {@inheritDoc} */
    protected function countRows(): int
    {
        return count($this->service->search($this->filters));
    }

    /** {@inheritDoc} */
    protected function findRecord(string $pk): ?array
    {
        $row = $this->service->getById((int) $pk);
        return empty($row) ? null : $row;
    }

    /** {@inheritDoc} */
    protected function createRecord(array $data)
    {
        return $this->saveOrFail($data, false);
    }

    /** {@inheritDoc} */
    protected function updateRecord(string $pk, array $data): void
    {
        $this->saveOrFail($data, true, (int) $pk);
    }

    /** {@inheritDoc} */
    protected function deleteRecord(string $pk): void
    {
        if ((int) $pk > 0) {
            $this->service->delete((int) $pk);
        }
    }

    /**
     * Create or update a run, turning a service refusal into an in-place error.
     *
     * @param array<string, mixed> $data    Form values
     * @param bool                 $isUpdate Whether this is an update
     * @param int                  $id       Record id when updating
     *
     * @return int New payroll id, 0 on refusal
     *
     * @since 1.1.0
     */
    private function saveOrFail(array $data, bool $isUpdate, int $id = 0): int
    {
        $payload = [
            'person_id'         => $data['person_id'] ?? 0,
            'pay_period_start'  => $data['pay_period_start'] ?? '',
            'pay_period_end'    => $data['pay_period_end'] ?? '',
            'pay_date'          => $data['pay_date'] ?? '',
            'gross_pay'         => $data['gross_pay'] ?? 0,
            'total_deductions'  => $data['total_deductions'] ?? 0,
            // Derived server-side, never taken from the browser.
            'net_pay'           => (float) ($data['gross_pay'] ?? 0) - (float) ($data['total_deductions'] ?? 0),
            'status'            => $data['status'] ?? 'Draft',
        ];

        $result = $isUpdate
            ? $this->service->updateRun($id, $payload)
            : $this->service->createRun($payload);

        if (!$result['ok']) {
            $this->actionError($result['error']);
            return 0;
        }
        return (int) ($result['payroll_id'] ?? $id);
    }

    /**
     * Entry-form values. Blank for add, stored figures for update.
     *
     * @return array<string, mixed>
     *
     * @since 1.1.0
     */
    protected function blankValues(): array
    {
        return [
            'person_id'         => '',
            'pay_period_start'  => '',
            'pay_period_end'    => '',
            'pay_date'          => '',
            'gross_pay'         => '',
            'total_deductions'  => '',
            'net_pay'           => 0,
            'status'            => 'Draft',
        ];
    }

    /**
     * @param string $value SQL date
     *
     * @return string Display date
     *
     * @since 1.1.0
     */
    private function formatDate(string $value): string
    {
        return $value !== '' && function_exists('sql2date') ? sql2date($value) : '';
    }

    /**
     * @param float $value
     *
     * @return string
     *
     * @since 1.1.0
     */
    private function formatMoney($value): string
    {
        return function_exists('price_format') ? price_format((float) $value) : (string) $value;
    }

    /**
     * Overlay the rejected POST values, then re-derive net pay from them.
     *
     * Net pay is a 'label' field, so it is not in $_POST and would otherwise
     * re-render as whatever the blank form held — showing the user a different
     * net figure from the one they were refused on.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     *
     * @since 1.1.0
     */
    protected function postedFormValues(array $values): array
    {
        $values = parent::postedFormValues($values);

        if (isset($values['gross_pay']) || isset($values['total_deductions'])) {
            $values['net_pay'] = (float) ($values['gross_pay'] ?? 0)
                - (float) ($values['total_deductions'] ?? 0);
        }

        return $values;
    }

    /**
     * Heading above the entry form.
     *
     * @return string
     *
     * @since 1.1.0
     */
    protected function getEntryFormTitle(): string
    {
        return $this->context->getRecordId() === ''
            ? $this->localise('New Payroll Run')
            : $this->localise('Edit Payroll Run');
    }

    /** {@inheritDoc} */
    protected function getActionLabels(): array
    {
        return [
            'edit'   => $this->localise('Edit'),
            'delete' => $this->localise('Delete'),
        ];
    }

    /** {@inheritDoc} */
    protected function getDeleteConfirmMessage(): string
    {
        return $this->localise('Delete this payroll run? Its earnings and deduction '
            . 'lines are deleted with it.');
    }

    /** {@inheritDoc} */
    protected function getTitle(): string
    {
        return $this->localise('Payroll Runs');
    }

    /** {@inheritDoc} */
    protected function renderNotes(): void
    {
        if (!function_exists('display_note')) {
            return;
        }
        display_note($this->localise('Net pay is recalculated by the server as gross pay '
            . 'less deductions; the value shown here is a preview and is not what gets '
            . 'stored. Individual earnings and deduction lines are held per payroll run '
            . 'and are not edited from this tab.'), 0);
    }
}