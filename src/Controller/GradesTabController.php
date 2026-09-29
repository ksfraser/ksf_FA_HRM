<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Controller;

use ksfraser\FrontAccounting\Common\App\AbstractTabController;
use ksfraser\FrontAccounting\HRM\Service\GradeService;

/**
 * GradesTabController — controller SRP for the HRM Grades tab.
 *
 * Renders the SUMMARY UI SRP (MasterSummaryTable) above and the ENTRY-FORM UI
 * SRP (FieldForm) below, matching the FA items.php "Sales Pricing" layout
 * contract from AbstractTabController. There is no "Add New Grade" link: the
 * form is always on the page, Edit pre-loads it, and the footer button flips
 * from Save to Update.
 *
 * Row delete is a soft delete via GradeService::deactivate() — a grade can be
 * referenced by existing employees, so the row is deactivated rather than
 * removed.
 *
 * PHP 7.3 compatible.
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §2/§10 (controller SRP + UI SRPs)
 * @BABOK Related: FR-HRM-001
 */
class GradesTabController extends AbstractTabController
{
    /** @var GradeService */
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
        $this->service = new GradeService();
    }

    /** {@inheritDoc} */
    protected function getPkField(): string
    {
        return 'grade_id';
    }

    /** {@inheritDoc} */
    protected function getFieldMetadata(): array
    {
        return [
            'entity'      => 'grade',
            'table'       => TB_PREF . 'hrm_grades',
            'label'       => 'Grade',
            'labelPlural' => 'Grades',
            'hookPrefix'  => 'grade',
            'pk'          => 'grade_id',
            'fields'      => [
                'grade_id' => [
                    'label' => 'ID',
                    'type'  => 'text',
                    'showInForm' => false,
                    'showInTable' => false,
                ],
                'grade_code' => [
                    'label' => 'Code',
                    'type'  => 'text',
                    'required' => true,
                    'max' => 20,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-2',
                ],
                'grade_name' => [
                    'label' => 'Name',
                    'type'  => 'text',
                    'required' => true,
                    'max' => 100,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-3',
                ],
                'min_salary' => [
                    'label' => 'Min Salary',
                    'type'  => 'number',
                    'step' => '0.01',
                    'min'  => '0',
                    'default' => 0,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-2',
                ],
                'max_salary' => [
                    'label' => 'Max Salary',
                    'type'  => 'number',
                    'step' => '0.01',
                    'min'  => '0',
                    'default' => 0,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-2',
                ],
                'description' => [
                    'label' => 'Description',
                    'type'  => 'textarea',
                    'rows' => 2,
                    'showInTable' => false,
                    'showInForm' => true,
                    'colClass' => 'col-md-8',
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
            'tableSettings' => ['orderBy' => 'grade_name ASC'],
        ];
    }

    /** {@inheritDoc} */
    protected function listRows(int $page, int $perPage): array
    {
        $rows = [];
        $offset = ($page - 1) * $perPage;
        foreach (array_slice($this->service->listAll(), $offset, $perPage) as $grade) {
            $rows[] = $grade->toArray();
        }
        return $rows;
    }

    /** {@inheritDoc} */
    protected function countRows(): int
    {
        return count($this->service->listAll());
    }

    /** {@inheritDoc} */
    protected function findRecord(string $pk): ?array
    {
        $grade = $this->service->getById((int) $pk);
        return empty($grade) ? null : $grade;
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
        $this->service->deactivate((int) $pk);
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
        return $this->localise('Deactivate this grade? Employees already assigned to it keep their grade.');
    }
}
