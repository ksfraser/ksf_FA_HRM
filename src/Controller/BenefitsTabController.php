<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Controller;

use ksfraser\FrontAccounting\Common\App\AbstractTabController;
use ksfraser\FrontAccounting\HRM\Service\BenefitsService;

/**
 * BenefitsTabController — controller SRP for the HRM Benefits tab.
 *
 * Benefit catalogue (the benefit types an employee can be assigned), not the
 * per-employee assignment list — that is a separate concern driven by
 * BenefitsService::getEmployeeBenefits().
 *
 * Row delete is a soft delete via BenefitsService::deactivate(), which clears
 * is_active only so historic assignments keep resolving their benefit name.
 *
 * PHP 7.3 compatible.
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §2/§10
 * @BABOK Related: FR-HRM-001
 */
class BenefitsTabController extends AbstractTabController
{
    /** @var BenefitsService */
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
        $this->service = new BenefitsService();
    }

    /** {@inheritDoc} */
    protected function getPkField(): string
    {
        return 'benefit_id';
    }

    /** {@inheritDoc} */
    protected function getFieldMetadata(): array
    {
        return [
            'entity'      => 'benefit',
            'table'       => TB_PREF . 'hrm_benefits',
            'label'       => 'Benefit',
            'labelPlural' => 'Benefits',
            'hookPrefix'  => 'benefit',
            'pk'          => 'benefit_id',
            'fields'      => [
                'benefit_id' => [
                    'label' => 'ID',
                    'type'  => 'text',
                    'showInForm' => false,
                    'showInTable' => false,
                ],
                'benefit_code' => [
                    'label' => 'Code',
                    'type'  => 'text',
                    'required' => true,
                    'max' => 20,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-2',
                ],
                'benefit_name' => [
                    'label' => 'Name',
                    'type'  => 'text',
                    'required' => true,
                    'max' => 100,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-3',
                ],
                'benefit_type' => [
                    'label' => 'Type',
                    'type'  => 'text',
                    'max' => 50,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-2',
                ],
                'employer_rate' => [
                    'label' => 'Employer Rate',
                    'type'  => 'number',
                    'step' => '0.01',
                    'default' => 0,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-2',
                ],
                'employee_rate' => [
                    'label' => 'Employee Rate',
                    'type'  => 'number',
                    'step' => '0.01',
                    'default' => 0,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-2',
                ],
                'fixed_amount' => [
                    'label' => 'Fixed Amount',
                    'type'  => 'number',
                    'step' => '0.01',
                    'default' => 0,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-2',
                ],
                'is_percentage_based' => [
                    'label' => 'Percentage Based',
                    'type'  => 'checkbox',
                    'default' => 0,
                    'showInTable' => false,
                    'showInForm' => true,
                    'colClass' => 'col-md-2',
                ],
                'is_mandatory' => [
                    'label' => 'Mandatory',
                    'type'  => 'checkbox',
                    'default' => 0,
                    'showInTable' => false,
                    'showInForm' => true,
                    'colClass' => 'col-md-2',
                ],
                'is_tax_deductible' => [
                    'label' => 'Tax Deductible',
                    'type'  => 'checkbox',
                    'default' => 0,
                    'showInTable' => false,
                    'showInForm' => true,
                    'colClass' => 'col-md-2',
                ],
                'provider' => [
                    'label' => 'Provider',
                    'type'  => 'text',
                    'max' => 100,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-3',
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
            'tableSettings' => ['orderBy' => 'benefit_name ASC'],
        ];
    }

    /** {@inheritDoc} */
    protected function listRows(int $page, int $perPage): array
    {
        $rows = [];
        $offset = ($page - 1) * $perPage;
        foreach (array_slice($this->service->listAll(), $offset, $perPage) as $benefit) {
            $rows[] = $benefit->toArray();
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
        $benefit = $this->service->getById((int) $pk);
        return empty($benefit) ? null : $benefit;
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
        return $this->localise('Deactivate this benefit? Existing employee assignments are kept.');
    }
}
