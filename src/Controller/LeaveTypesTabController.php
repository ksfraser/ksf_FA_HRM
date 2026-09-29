<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Controller;

use ksfraser\FrontAccounting\Common\App\AbstractTabController;
use ksfraser\FrontAccounting\HRM\Repository\LookupRepository;

/**
 * LeaveTypesTabController — controller SRP for the HRM Leave Types tab.
 *
 * Leave types have no service or entity layer: the catalogue is a plain
 * row-array table (0_leave_types) read and written through LookupRepository,
 * which is also where the leave request join sources type_name. This
 * controller therefore talks to the repository directly rather than
 * introducing a service wrapper for one tab.
 *
 * Row delete is a soft delete via deactivateLeaveType() so historic leave
 * requests keep resolving their type_name through the join in leave.php.
 *
 * PHP 7.3 compatible.
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §2/§10
 * @BABOK Related: FR-HRM-001
 */
class LeaveTypesTabController extends AbstractTabController
{
    /** @var LookupRepository */
    private $repo;

    /**
     * @param \Ksfraser\Frontaccounting\HTML\TabContext|null $context DI request state
     * @param array<string, mixed>                            $options
     *
     * @since 1.0.0
     */
    public function __construct($context = null, array $options = [])
    {
        parent::__construct($context, $options);
        $this->repo = new LookupRepository();
    }

    /** {@inheritDoc} */
    protected function getPkField(): string
    {
        return 'leave_type_id';
    }

    /** {@inheritDoc} */
    protected function getFieldMetadata(): array
    {
        return [
            'entity'      => 'leaveType',
            'table'       => TB_PREF . 'leave_types',
            'label'       => 'Leave Type',
            'labelPlural' => 'Leave Types',
            'hookPrefix'  => 'leaveType',
            'pk'          => 'leave_type_id',
            'fields'      => [
                'leave_type_id' => [
                    'label' => 'ID',
                    'type'  => 'text',
                    'showInForm' => false,
                    'showInTable' => false,
                ],
                'type_code' => [
                    'label' => 'Code',
                    'type'  => 'text',
                    'required' => true,
                    'max' => 20,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-3',
                ],
                'type_name' => [
                    'label' => 'Name',
                    'type'  => 'text',
                    'required' => true,
                    'max' => 100,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-4',
                ],
                'default_days' => [
                    'label' => 'Default Days',
                    'type'  => 'number',
                    'step' => '0',
                    'min'  => '0',
                    'default' => 0,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-2',
                ],
                'is_paid' => [
                    'label' => 'Paid',
                    'type'  => 'checkbox',
                    'default' => 1,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-2',
                ],
                'is_active' => [
                    'label' => 'Active',
                    'type'  => 'checkbox',
                    'default' => 1,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-2',
                ],
            ],
            'fk_ddls'   => [],
            'ddlHooks'  => [],
            'tableSettings' => ['orderBy' => 'type_name ASC'],
        ];
    }

    /** {@inheritDoc} */
    protected function listRows(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        return array_slice($this->repo->getLeaveTypes(), $offset, $perPage);
    }

    /** {@inheritDoc} */
    protected function countRows(): int
    {
        return count($this->repo->getLeaveTypes());
    }

    /** {@inheritDoc} */
    protected function findRecord(string $pk): ?array
    {
        foreach ($this->repo->getLeaveTypes() as $row) {
            if ((string) ($row['leave_type_id'] ?? '') === $pk) {
                return $row;
            }
        }
        return null;
    }

    /** {@inheritDoc} */
    protected function createRecord(array $data)
    {
        return $this->repo->saveLeaveType($data);
    }

    /** {@inheritDoc} */
    protected function updateRecord(string $pk, array $data): void
    {
        $this->repo->updateLeaveType((int) $pk, $data);
    }

    /** {@inheritDoc} */
    protected function deleteRecord(string $pk): void
    {
        $this->repo->deactivateLeaveType((int) $pk);
    }

    /** {@inheritDoc} */
    protected function getActionLabels(): array
    {
        return [
            'edit'   => $this->localise('Edit'),
            'delete' => $this->localise('Deactivate'),
        ];
    }

    /** {@inheritDoc} */
    protected function getDeleteConfirmMessage(): string
    {
        return $this->localise('Deactivate this leave type? Existing leave requests keep their type.');
    }
}
