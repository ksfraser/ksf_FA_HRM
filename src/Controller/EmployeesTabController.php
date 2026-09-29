<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Controller;

use ksfraser\FrontAccounting\Common\App\AbstractTabController;
use ksfraser\FrontAccounting\HRM\Exception\EmployeeNotFoundException;
use ksfraser\FrontAccounting\HRM\Repository\LookupRepository;
use ksfraser\FrontAccounting\HRM\Service\DepartmentService;
use ksfraser\FrontAccounting\HRM\Service\EmployeeService;
use ksfraser\FrontAccounting\HRM\Service\GradeService;
use ksfraser\FrontAccounting\HRM\Service\PositionService;

/**
 * EmployeesTabController — controller SRP for the HRM Employees tab.
 *
 * The employment record's primary key is employment_id, not person_id: an
 * employee is a hire event against a CRM contact (person_id), and one contact
 * can be employed more than once across time.
 *
 * The row delete action maps to EmployeeService::terminate(), which sets
 * is_active = 0 and stamps termination_date. The button is therefore labelled
 * "Terminate" rather than "Delete" — an employment record is never removed.
 * This is why the summary table exposes overridable action labels: without
 * them the shared base would hardcode "Delete" and misdescribe the action.
 *
 * EmployeeService::getById() throws EmployeeNotFoundException rather than
 * returning an empty array like the other HRM services, so findRecord() has to
 * translate that into the nullable return the controller contract expects.
 *
 * PHP 7.3 compatible.
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §2/§10
 * @BABOK Related: FR-HRM-001
 */
class EmployeesTabController extends AbstractTabController
{
    /** @var EmployeeService */
    private $service;

    /** @var DepartmentService */
    private $departmentService;

    /** @var PositionService */
    private $positionService;

    /** @var GradeService */
    private $gradeService;

    /** @var LookupRepository */
    private $lookupRepo;

    /**
     * @param \Ksfraser\Frontaccounting\HTML\TabContext|null $context DI request state
     * @param array<string, mixed>                            $options
     *
     * @since 1.0.0
     */
    public function __construct($context = null, array $options = [])
    {
        parent::__construct($context, $options);
        $this->service = new EmployeeService();
        $this->departmentService = new DepartmentService();
        $this->positionService = new PositionService();
        $this->gradeService = new GradeService();
        $this->lookupRepo = new LookupRepository();
    }

    /** {@inheritDoc} */
    protected function getPkField(): string
    {
        return 'employment_id';
    }

    /** {@inheritDoc} */
    protected function getFieldMetadata(): array
    {
        return [
            'entity'      => 'employee',
            'table'       => TB_PREF . 'hrm_contacts_employment',
            'label'       => 'Employee',
            'labelPlural' => 'Employees',
            'hookPrefix'  => 'employee',
            'pk'          => 'employment_id',
            'fields'      => [
                'employment_id' => [
                    'label' => 'ID',
                    'type'  => 'text',
                    'showInForm' => false,
                    'showInTable' => false,
                ],
                'person_id' => [
                    'label' => 'Contact',
                    'type'  => 'select',
                    'required' => true,
                    'showInTable' => false,
                    'showInForm' => true,
                    'colClass' => 'col-md-4',
                ],
                'employee_code' => [
                    'label' => 'Employee Code',
                    'type'  => 'text',
                    'required' => true,
                    'max' => 20,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-2',
                ],
                'department_id' => [
                    'label' => 'Department',
                    'type'  => 'select',
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-3',
                ],
                'position_id' => [
                    'label' => 'Position',
                    'type'  => 'select',
                    'showInTable' => false,
                    'showInForm' => true,
                    'colClass' => 'col-md-3',
                ],
                'grade_id' => [
                    'label' => 'Grade',
                    'type'  => 'select',
                    'showInTable' => false,
                    'showInForm' => true,
                    'colClass' => 'col-md-3',
                ],
                'hire_date' => [
                    'label' => 'Hire Date',
                    'type'  => 'date',
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-3',
                ],
                'probation_end_date' => [
                    'label' => 'Probation End',
                    'type'  => 'date',
                    'showInTable' => false,
                    'showInForm' => true,
                    'colClass' => 'col-md-3',
                ],
                'reports_to_person_id' => [
                    'label' => 'Reports To',
                    'type'  => 'select',
                    'showInTable' => false,
                    'showInForm' => true,
                    'colClass' => 'col-md-3',
                ],
                'termination_date' => [
                    'label' => 'Termination Date',
                    'type'  => 'date',
                    'showInTable' => true,
                    'showInForm' => false,
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
            'tableSettings' => ['orderBy' => 'employee_code ASC'],
        ];
    }

    /**
     * Contact, department, position, grade and manager selects.
     *
     * @return array<string, array<string, string>>
     */
    protected function fkOptions(): array
    {
        $persons = [];
        foreach ($this->lookupRepo->getCrmPersons() as $row) {
            $persons[(string) $row['id']] = (string) $row['name'];
        }

        return [
            'person_id'            => $persons,
            'department_id'        => $this->toOptionMap($this->departmentService->getHtmlOptions(true, $this->localise('None'))),
            'position_id'          => $this->toOptionMap($this->positionService->getHtmlOptions(true, $this->localise('None'))),
            'grade_id'             => $this->toOptionMap($this->gradeService->getHtmlOptions(true, $this->localise('None'))),
            'reports_to_person_id' => $persons,
        ];
    }

    /**
     * @param array $options HtmlOption[] from a service getHtmlOptions() call
     * @return array<string, string> value => label
     */
    private function toOptionMap(array $options): array
    {
        $map = [];
        foreach ($options as $option) {
            if (is_object($option) && method_exists($option, 'getValue')) {
                $map[(string) $option->getValue()] = (string) $option->getLabel();
            }
        }
        return $map;
    }

    /** {@inheritDoc} */
    protected function listRows(int $page, int $perPage): array
    {
        $rows = [];
        $offset = ($page - 1) * $perPage;
        foreach (array_slice($this->service->listAll(), $offset, $perPage) as $employee) {
            $rows[] = $employee->toArray();
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
        try {
            return $this->service->getById((int) $pk);
        } catch (EmployeeNotFoundException $e) {
            return null;
        }
    }

    /** {@inheritDoc} */
    protected function createRecord(array $data)
    {
        return $this->service->hire($data);
    }

    /** {@inheritDoc} */
    protected function updateRecord(string $pk, array $data): void
    {
        $this->service->updateEmployee((int) $pk, $data);
    }

    /** {@inheritDoc} */
    protected function deleteRecord(string $pk): void
    {
        $this->service->terminate((int) $pk);
    }

    /** {@inheritDoc} */
    protected function getActionLabels(): array
    {
        return [
            'edit'   => $this->localise('Edit'),
            'delete' => $this->localise('Terminate'),
        ];
    }

    /** {@inheritDoc} */
    protected function getDeleteConfirmMessage(): string
    {
        return $this->localise('Terminate this employment record? The record is kept for history.');
    }
}
