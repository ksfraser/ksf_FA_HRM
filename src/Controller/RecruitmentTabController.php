<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Controller;

use ksfraser\FrontAccounting\HRM\Service\DepartmentService;

/**
 * RecruitmentTabController — HRM Recruitment tab.
 *
 * Replaces pages/recruitment.php. This tab is a deliberate PLACEHOLDER.
 *
 * ksf_FA_HRM's schema owns no vacancy / candidate / application table, so
 * there is nothing to read and nothing to write. Rather than render a page
 * whose SQL would be invented against tables that do not exist — which is
 * precisely the class of bug the pages/ scripts were consolidated to remove —
 * the tab renders the full filter / summary / entry-form structure and reports
 * an empty candidate list. When 0_hrm_vacancies and 0_hrm_candidates land,
 * only listRows() / countRows() / findRecord() / createRecord() /
 * updateRecord() / deleteRecord() below need real bodies, behind an
 * HrmRecruitmentService; the layout, filter and form handling here do not
 * change.
 *
 * Mutations are refused explicitly rather than silently succeeding, so a
 * candidate typed into the form cannot look like it was stored.
 *
 * PHP 7.3 compatible.
 *
 * @package ksf_FA_HRM
 * @since   1.1.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §11 (tab controller SRP)
 * @BABOK Related: FR-HRM-001 (recruitment — placeholder)
 */
class RecruitmentTabController extends FilteredTabController
{
    /** @var DepartmentService */
    private $departments;

    /**
     * @param mixed                $context DI request state
     * @param array<string, mixed> $options
     *
     * @since 1.1.0
     */
    public function __construct($context = null, array $options = [])
    {
        parent::__construct($context, $options);
        $this->departments = new DepartmentService();
    }

    /** {@inheritDoc} */
    protected function getPkField(): string
    {
        return 'candidate_id';
    }

    /** {@inheritDoc} */
    protected function getFieldMetadata(): array
    {
        return [
            'entity'      => 'candidate',
            'table'       => TB_PREF . 'hrm_candidates',
            'label'       => 'Candidate',
            'labelPlural' => 'Candidates',
            'hookPrefix'  => 'candidate',
            'pk'          => 'candidate_id',
            'fields'      => [
                'candidate_id' => [
                    'label' => 'ID', 'type' => 'text',
                    'showInForm' => false, 'showInTable' => false,
                ],
                'candidate_name' => [
                    'label' => 'Candidate Name', 'type' => 'text',
                    'showInTable' => true, 'showInForm' => true,
                ],
                'position_title' => [
                    'label' => 'Position', 'type' => 'text',
                    'showInTable' => true, 'showInForm' => true,
                ],
                'department_id' => [
                    'label' => 'Department', 'type' => 'select',
                    'showInTable' => true, 'showInForm' => true,
                ],
                'applied_on' => [
                    'label' => 'Applied On', 'type' => 'date',
                    'showInTable' => true, 'showInForm' => true,
                ],
                'candidate_status' => [
                    'label' => 'Status', 'type' => 'select', 'default' => 'New',
                    'showInTable' => true, 'showInForm' => true,
                ],
            ],
            'fk_ddls'   => [],
            'ddlHooks'  => [],
            'tableSettings' => [],
        ];
    }

    /**
     * Department options for the filter and the form.
     *
     * @return array<string, array<string, string>>
     *
     * @since 1.1.0
     */
    protected function fkOptions(): array
    {
        return [
            'department_id'   => $this->departments->optionsFor(),
            'candidate_status' => $this->statusOptions(),
        ];
    }

    /**
     * Candidate pipeline statuses.
     *
     * @return array<string, string>
     *
     * @since 1.1.0
     */
    private function statusOptions(): array
    {
        $statuses = ['New', 'Screening', 'Interview', 'Offered', 'Hired', 'Rejected'];
        $out = [];
        foreach ($statuses as $status) {
            $out[$status] = $this->localise($status);
        }
        return $out;
    }

    /** {@inheritDoc} */
    protected function getFilterFields(): array
    {
        return [
            ['name' => 'filter_department_id', 'label' => $this->localise('Department:'),
                'type' => 'select', 'options' => $this->departments->optionsFor(),
                'spec_option' => $this->localise('All departments')],
            ['name' => 'filter_name_fragment', 'label' => $this->localise('Name Contains:'),
                'type' => 'text', 'size' => 30, 'max' => 60],
        ];
    }

    /** {@inheritDoc} */
    protected function normaliseFilters(array $input): array
    {
        return [
            'department_id'  => (int) ($input['filter_department_id'] ?? 0),
            'name_fragment'  => (string) ($input['filter_name_fragment'] ?? ''),
        ];
    }

    /**
     * No candidate store exists yet; see the class header.
     *
     * @param int $page
     * @param int $perPage
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 1.1.0
     */
    protected function listRows(int $page, int $perPage): array
    {
        return [];
    }

    /**
     * @return int
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
     * @return array<string, mixed>|null Always null — nothing is stored.
     *
     * @since 1.1.0
     */
    protected function findRecord(string $pk): ?array
    {
        return null;
    }

    /**
     * Refuse: the persistence layer for this tab does not exist.
     *
     * @param array<string, mixed> $data
     *
     * @return int Always 0
     *
     * @since 1.1.0
     */
    protected function createRecord(array $data)
    {
        $this->actionError($this->notImplemented());
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
        $this->actionError($this->notImplemented());
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
        $this->actionError($this->notImplemented());
    }

    /**
     * @return string
     *
     * @since 1.1.0
     */
    private function notImplemented(): string
    {
        return $this->localise('Recruitment is not implemented in this module: ksf_FA_HRM '
            . 'ships no vacancy, candidate or application tables, so this entry cannot '
            . 'be stored.');
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
        return $this->localise('Add Candidate');
    }

    /**
     * The empty-list message names the reason: a filter that matches nothing
     * reads very differently from a feature that stores nothing.
     *
     * @return string
     *
     * @since 1.1.0
     */
    protected function getEmptyMessage(): string
    {
        $active = (int) ($this->filters['department_id'] ?? 0) > 0
            || trim((string) ($this->filters['name_fragment'] ?? '')) !== '';

        return $active
            ? $this->localise('No candidates match the current filter.')
            : $this->localise('No candidates have been recorded yet.');
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
    protected function getTitle(): string
    {
        return $this->localise('Candidates');
    }

    /** {@inheritDoc} */
    protected function renderNotes(): void
    {
        if (function_exists('display_note')) {
            display_note($this->notImplemented() . ' ' . $this->localise(
                'The structure is in place and the access area (SA_HRM_RECRUITMENT) is '
                . 'real; the persistence layer is the missing piece.'), 0);
        }
    }
}