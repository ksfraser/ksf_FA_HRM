<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Controller;

use ksfraser\FrontAccounting\Common\App\AbstractTabController;
use ksfraser\FrontAccounting\HRM\Service\DepartmentService;

/**
 * DepartmentsTabController — controller SRP for the HRM Departments tab.
 *
 * Coordinates the page flow for the Departments view: routes save/update/
 * delete, then renders the SUMMARY UI SRP (MasterSummaryTable) above and the
 * ENTRY-FORM UI SRP (FieldForm) below — matching the FA items.php "Sales
 * Pricing" layout contract from AbstractTabController.
 *
 * Data access is delegated to the DI'd DepartmentService (DAO-backed). Field
 * metadata follows the FR-006-007 schema consumed by TableView/FieldForm.
 *
 * PHP 7.3 compatible.
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §2/§10 (controller SRP + UI SRPs)
 * @BABOK Related: FR-HRM-001, FR-006-007
 */
class DepartmentsTabController extends AbstractTabController
{
    /** @var DepartmentService */
    private $service;

    /**
     * @param \Ksfraser\Frontaccounting\HTML\TabContext|null $context DI request state
     * @param array<string, mixed>                            $options
     *
     * @since 1.0.0
     */
    public function __construct($context = null, array $options = [])
    {
        parent::__construct($context, $options);
        $this->service = new DepartmentService();
    }

    /** {@inheritDoc} */
    protected function getPkField(): string
    {
        return 'department_id';
    }

    /** {@inheritDoc} */
    protected function getFieldMetadata(): array
    {
        return [
            'entity'      => 'department',
            'table'       => TB_PREF . 'hrm_departments',
            'label'       => 'Department',
            'labelPlural' => 'Departments',
            'hookPrefix'  => 'department',
            'pk'          => 'department_id',
            'fields'      => [
                'department_id' => [
                    'label' => 'ID',
                    'type' => 'text',
                    'showInForm' => false,
                    'showInTable' => true,
                ],
                'department_code' => [
                    'label' => 'Code',
                    'type' => 'text',
                    'required' => true,
                    'max' => 20,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-2',
                ],
                'department_name' => [
                    'label' => 'Name',
                    'type' => 'text',
                    'required' => true,
                    'max' => 100,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-4',
                ],
                'description' => [
                    'label' => 'Description',
                    'type' => 'textarea',
                    'rows' => 2,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-6',
                ],
                'is_active' => [
                    'label' => 'Active',
                    'type' => 'checkbox',
                    'default' => 1,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-2',
                ],
            ],
            'fk_ddls'   => [],
            'ddlHooks'  => [],
            'tableSettings' => ['orderBy' => 'department_code ASC'],
        ];
    }

    /** {@inheritDoc} */
    protected function listRows(int $page, int $perPage): array
    {
        $rows = [];
        $offset = ($page - 1) * $perPage;
        foreach (array_slice($this->service->getDepartments(false), $offset, $perPage) as $department) {
            $rows[] = $department->toArray();
        }
        return $rows;
    }

    /** {@inheritDoc} */
    protected function countRows(): int
    {
        return count($this->service->getDepartments(false));
    }

    /** {@inheritDoc} */
    protected function findRecord(string $pk): ?array
    {
        $department = $this->service->getById((int) $pk);
        return $department !== null ? $department->toArray() : null;
    }

    /** {@inheritDoc} */
    protected function createRecord(array $data)
    {
        return $this->service->create($data);
    }

    /** {@inheritDoc} */
    protected function updateRecord(string $pk, array $data): void
    {
        $this->service->update((int) $pk, $data);
    }

    /** {@inheritDoc} */
    protected function deleteRecord(string $pk): void
    {
        $this->service->delete((int) $pk);
    }
}