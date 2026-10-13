<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Controller;

use ksfraser\FrontAccounting\HRM\Service\LeaveBalanceService;

/**
 * LeaveTabController — HRM Leave Management tab.
 *
 * Replaces pages/leave.php. Reads go through LeaveBalanceService::search();
 * writes go through allocate() / release(). No SQL in this class.
 *
 * allocate() is deliberately *additive* on (person, type, year): days typed
 * against an existing allocation increase it rather than creating a second
 * row. The entry form therefore reads as "Allocate Leave" / "Adjust Leave
 * Balance" rather than a plain Save/Update, and updateRecord() subtracts the
 * stored figures first so an edit replaces rather than compounds.
 *
 * release() refuses when leave has already been taken against the balance, so
 * the Delete row action surfaces that message rather than silently failing.
 *
 * PHP 7.3 compatible.
 *
 * @package ksf_FA_HRM
 * @since   1.1.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §11 (tab controller SRP)
 * @BABOK Related: FR-HRM-001-005 (leave balances)
 */
class LeaveTabController extends FilteredTabController
{
    /** @var LeaveBalanceService */
    private $service;

    /**
     * @param mixed                            $context DI request state
     * @param array<string, mixed>             $options
     *
     * @since 1.1.0
     */
    public function __construct($context = null, array $options = [])
    {
        parent::__construct($context, $options);
        $this->service = new LeaveBalanceService();
    }

    /** {@inheritDoc} */
    protected function getPkField(): string
    {
        return 'balance_id';
    }

    /** {@inheritDoc} */
    protected function getFieldMetadata(): array
    {
        return [
            'entity'      => 'leave_balance',
            'table'       => TB_PREF . 'hrm_leave_balances',
            'label'       => 'Leave Balance',
            'labelPlural' => 'Leave Balances',
            'hookPrefix'  => 'leave_balance',
            'pk'          => 'balance_id',
            'fields'      => [
                'balance_id' => [
                    'label' => 'ID', 'type' => 'text',
                    'showInForm' => false, 'showInTable' => false,
                ],
                // employee_name / leave_type_name are display-only columns the
                // repository resolves (LEFT JOIN crm_persons, and the optional
                // leave-type master). The form edits the underlying ids.
                'employee_name' => [
                    'label' => 'Employee', 'type' => 'text',
                    'showInTable' => true, 'showInForm' => false,
                ],
                'leave_type_name' => [
                    'label' => 'Leave Type', 'type' => 'text',
                    'showInTable' => true, 'showInForm' => false,
                ],
                'person_id' => [
                    'label' => 'Employee', 'type' => 'select',
                    'showInTable' => false, 'showInForm' => true,
                ],
                'leave_type_id' => [
                    'label' => 'Leave Type', 'type' => 'select',
                    'showInTable' => false, 'showInForm' => true,
                ],
                'year' => [
                    'label' => 'Year', 'type' => 'text',
                    'showInTable' => true, 'showInForm' => true,
                ],
                'allocated_days' => [
                    'label' => 'Allocated', 'type' => 'number', 'dec' => 2,
                    'showInTable' => true, 'showInForm' => true,
                ],
                'carried_over' => [
                    'label' => 'Carried Over', 'type' => 'number', 'dec' => 2,
                    'showInTable' => true, 'showInForm' => true,
                ],
                'used_days' => [
                    'label' => 'Used', 'type' => 'number', 'dec' => 2,
                    'showInTable' => true, 'showInForm' => false,
                ],
                'balance' => [
                    'label' => 'Balance', 'type' => 'number', 'dec' => 2,
                    'showInTable' => true, 'showInForm' => false,
                ],
            ],
            'fk_ddls'   => [],
            'ddlHooks'  => [],
            'tableSettings' => [],
        ];
    }

    /**
     * Employee and leave-type option lists for the form and the filter.
     *
     * @return array<string, array<string, string>>
     *
     * @since 1.1.0
     */
    protected function fkOptions(): array
    {
        return [
            'person_id'     => $this->service->personOptions(),
            'leave_type_id' => $this->service->leaveTypeOptions(),
        ];
    }

    /** {@inheritDoc} */
    protected function getFilterFields(): array
    {
        $options = $this->fkOptions();

        return [
            ['name' => 'filter_person_id', 'label' => $this->localise('Employee:'),
                'type' => 'select', 'options' => $options['person_id'],
                'spec_option' => $this->localise('All employees')],
            ['name' => 'filter_leave_type_id', 'label' => $this->localise('Leave Type:'),
                'type' => 'select', 'options' => $options['leave_type_id'],
                'spec_option' => $this->localise('All leave types')],
            ['name' => 'filter_year', 'label' => $this->localise('Leave Year:'),
                'type' => 'text', 'size' => 4, 'max' => 4],
        ];
    }

    /** {@inheritDoc} */
    protected function normaliseFilters(array $input): array
    {
        return [
            'person_id'     => (int) ($input['filter_person_id'] ?? 0),
            'leave_type_id' => (int) ($input['filter_leave_type_id'] ?? 0),
            'year'          => (int) ($input['filter_year'] ?? 0),
        ];
    }

    /**
     * Coerce raw request input into typed filter values.
     *
     * @param array<string, mixed> $filters
     *
     * @return array<string, int>
     *
     * @since 1.1.0
     */
    protected function serviceFilters(array $filters): array
    {
        return [
            'person_id'     => (int) ($filters['person_id'] ?? 0),
            'leave_type_id' => (int) ($filters['leave_type_id'] ?? 0),
            'year'          => (int) ($filters['year'] ?? 0),
        ];
    }

    /** {@inheritDoc} */
    protected function listRows(int $page, int $perPage): array
    {
        $rows = [];
        $offset = ($page - 1) * $perPage;
        $all = $this->service->search($this->serviceFilters($this->filters));
        foreach (array_slice($all, $offset, $perPage) as $balance) {
            $row = $balance;
            $row['employee_name'] = !empty($balance['employee_name'])
                ? $balance['employee_name'] : $this->localise('(unknown)');
            $rows[] = $row;
        }
        return $rows;
    }

    /** {@inheritDoc} */
    protected function countRows(): int
    {
        return count($this->service->search($this->serviceFilters($this->filters)));
    }

    /** {@inheritDoc} */
    protected function findRecord(string $pk): ?array
    {
        return $this->service->find((int) $pk);
    }

    /** {@inheritDoc} */
    protected function createRecord(array $data)
    {
        return $this->allocateOrFail($data);
    }

    /**
     * Update an existing allocation.
     *
     * allocate() adds to the stored figure, so an edit must first take the
     * stored allocation back out. Releasing and re-allocating is not an option
     * (release() refuses once leave has been taken), so the delta is applied
     * directly through the service's allocate() with the difference.
     *
     * @param string              $pk
     * @param array<string,mixed> $data
     *
     * @return void
     *
     * @since 1.1.0
     */
    protected function updateRecord(string $pk, array $data): void
    {
        $existing = $this->service->find((int) $pk);
        if ($existing === null) {
            $this->actionError($this->localise('That leave balance no longer exists.'));
            return;
        }

        $this->allocateOrFail($data, [
            'allocated_days' => (float) ($data['allocated_days'] ?? 0) - (float) $existing['allocated_days'],
            'carried_over'   => (float) ($data['carried_over'] ?? 0) - (float) $existing['carried_over'],
        ]);
    }

    /**
     * Delete a balance row.
     *
     * @param string $pk
     *
     * @return void
     *
     * @since 1.1.0
     */
    protected function deleteRecord(string $pk): void
    {
        $result = $this->service->release((int) $pk);
        if (!$result['ok']) {
            $this->actionError($result['error']);
        }
    }

    /**
     * Allocate, turning a service refusal into an in-place error rather than a
     * silent success.
     *
     * @param array<string,mixed>      $data  Form values
     * @param array<string,mixed>|null $delta Optional override for the day counts
     *
     * @return int New/affected balance id, 0 on refusal
     *
     * @since 1.1.0
     */
    private function allocateOrFail(array $data, ?array $delta = null): int
    {
        $posted = [
            'person_id'      => $data['person_id'] ?? 0,
            'leave_type_id'  => $data['leave_type_id'] ?? 0,
            'year'           => $data['year'] ?? 0,
            'allocated_days' => $delta['allocated_days'] ?? ($data['allocated_days'] ?? 0),
            'carried_over'   => $delta['carried_over'] ?? ($data['carried_over'] ?? 0),
        ];

        $result = $this->service->allocate($posted);
        if (!$result['ok']) {
            $this->actionError($result['error']);
            return 0;
        }
        return (int) $result['balance_id'];
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
            ? $this->localise('Allocate Leave')
            : $this->localise('Adjust Leave Balance');
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
        return $this->localise('Release this leave balance? Only balances with nothing '
            . 'taken against them can be released.');
    }

    /** {@inheritDoc} */
    protected function getTitle(): string
    {
        return $this->localise('Leave Balances');
    }

    /** {@inheritDoc} */
    protected function renderNotes(): void
    {
        if (!function_exists('display_note')) {
            return;
        }
        display_note($this->localise('Days entered against an existing employee / type / '
            . 'year are added to the stored allocation. Consuming a balance happens '
            . 'through the leave approval workflow, which increments used days when a '
            . 'request is approved.'), 0);
    }
}