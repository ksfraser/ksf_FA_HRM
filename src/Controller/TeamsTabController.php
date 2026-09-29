<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Controller;

use ksfraser\FrontAccounting\Common\App\AbstractTabController;
use ksfraser\FrontAccounting\HRM\Service\DepartmentService;
use ksfraser\FrontAccounting\HRM\Service\TeamService;

/**
 * TeamsTabController — controller SRP for the HRM Teams tab.
 *
 * This tab was previously unreachable: pages/teams.php existed with the
 * add-link + table layout but was never registered in HrmAppShell, so
 * dispatch() had no tab to resolve and the page could never render. The tab
 * is now registered and controller-backed.
 *
 * Row delete is a soft delete via TeamService::deactivate() — teams are
 * referenced by hrm_positions.team_id and by hrm_teams.parent_team_id.
 *
 * PHP 7.3 compatible.
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §2/§10
 * @BABOK Related: FR-HRM-001
 */
class TeamsTabController extends AbstractTabController
{
    /** @var TeamService */
    private $service;

    /** @var DepartmentService */
    private $departmentService;

    /**
     * @param \Ksfraser\Frontaccounting\HTML\TabContext|null $context DI request state
     * @param array<string, mixed>                            $options
     *
     * @since 1.0.0
     */
    public function __construct($context = null, array $options = [])
    {
        parent::__construct($context, $options);
        $this->service = new TeamService();
        $this->departmentService = new DepartmentService();
    }

    /** {@inheritDoc} */
    protected function getPkField(): string
    {
        return 'team_id';
    }

    /** {@inheritDoc} */
    protected function getFieldMetadata(): array
    {
        return [
            'entity'      => 'team',
            'table'       => TB_PREF . 'hrm_teams',
            'label'       => 'Team',
            'labelPlural' => 'Teams',
            'hookPrefix'  => 'team',
            'pk'          => 'team_id',
            'fields'      => [
                'team_id' => [
                    'label' => 'ID',
                    'type'  => 'text',
                    'showInForm' => false,
                    'showInTable' => false,
                ],
                'team_code' => [
                    'label' => 'Code',
                    'type'  => 'text',
                    'required' => true,
                    'max' => 20,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-2',
                ],
                'team_name' => [
                    'label' => 'Name',
                    'type'  => 'text',
                    'required' => true,
                    'max' => 100,
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-3',
                ],
                'department_id' => [
                    'label' => 'Department',
                    'type'  => 'select',
                    'showInTable' => true,
                    'showInForm' => true,
                    'colClass' => 'col-md-3',
                ],
                'parent_team_id' => [
                    'label' => 'Parent Team',
                    'type'  => 'select',
                    'showInTable' => false,
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
            'tableSettings' => ['orderBy' => 'team_name ASC'],
        ];
    }

    /**
     * Department + parent-team selects. Service getHtmlOptions() returns
     * HtmlOption[] objects; FieldForm wants a plain value => label map.
     *
     * @return array<string, array<string, string>>
     */
    protected function fkOptions(): array
    {
        return [
            'department_id'  => $this->toOptionMap($this->departmentService->getHtmlOptions(true, $this->localise('All'))),
            'parent_team_id' => $this->toOptionMap($this->service->getHtmlOptions(true, $this->localise('None'))),
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
        foreach (array_slice($this->service->listAll(), $offset, $perPage) as $team) {
            $rows[] = $team->toArray();
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
        return $this->service->getById((int) $pk);
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
        return $this->localise('Deactivate this team? Positions assigned to it keep their team.');
    }
}
