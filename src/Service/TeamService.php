<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Service;

use ksfraser\FrontAccounting\HRM\Repository\TeamRepository;
use ksfraser\FrontAccounting\HRM\Entity\Team;
use Ksfraser\HTML\Elements\HtmlOption;
use Ksfraser\HTML\Elements\HtmlSelect;
use Ksfraser\HTML\Traits\DdlCacheTrait;

/**
 * TeamService — DDL caching + hooks for team reference data.
 *
 * @see BR-006 (Cross-Module DDL Caching)
 * @see FR-006-001 (Three-Layer Cache Architecture)
 *
 * @since 1.0.0
 */
class TeamService
{
    use DdlCacheTrait;

    /** @var TeamRepository */
    private $repo;

    /** @var array[]|null Entity cache */
    /** @var array|null */
    private static $entityCache = null;

    public function __construct(?TeamRepository $repo = null)
    {
        $this->repo = $repo ?? new TeamRepository();
    }

    // ─── Entity Access ─────────────────────────────────────────────

    /**
     * Get teams (cached).
     *
     * @param bool $activeOnly
     * @return Team[]
     */
    public function getEntities(bool $activeOnly = true): array
    {
        $key = $activeOnly ? 'active' : 'all';
        if (self::$entityCache !== null && isset(self::$entityCache[$key])) {
            return self::$entityCache[$key];
        }
        $entities = $activeOnly ? $this->repo->findActiveByDepartment(0) : $this->repo->findAll();
        self::$entityCache[$key] = $entities;
        return $entities;
    }

    public function listAll(): array
    {
        return $this->repo->findAll();
    }

    public function getById(int $id): ?array
    {
        $entity = $this->repo->findById($id);
        return $entity ? $entity->toArray() : null;
    }

    public function getParentTeams(int $departmentId): array
    {
        return $this->repo->findByDepartment($departmentId);
    }

    public function getTeamsForDepartment(int $departmentId): array
    {
        return $this->repo->findActiveByDepartment($departmentId);
    }

    // ─── DDL (via DdlCacheTrait) ───────────────────────────────────

    public function getHtmlOptions(
        bool $activeOnly = true,
        string $blankLabel = '',
        string $formatString = '{code} - {name}',
        int $selectedId = 0
    ): array {
        $dataKey = ($activeOnly ? 'active' : 'all') . '|' . $blankLabel . '|' . $formatString;

        $options = $this->getOrBuildOptions($dataKey, function () use ($activeOnly, $blankLabel, $formatString) {
            $entities = $this->getEntities($activeOnly);
            $opts = [];
            if ($blankLabel !== '') {
                $opts[] = new HtmlOption('', $blankLabel);
            }
            foreach ($entities as $entity) {
                $text = str_replace(
                    ['{code}', '{name}', '{id}'],
                    [$entity->getTeamCode(), $entity->getTeamName(), (string)$entity->getTeamId()],
                    $formatString
                );
                $opts[] = new HtmlOption((string)$entity->getTeamId(), $text);
            }
            return $opts;
        });

        if ($selectedId > 0) {
            $cloned = [];
            foreach ($options as $option) {
                $clone = clone $option;
                $clone->setSelected($clone->getValue() === (string)$selectedId);
                $cloned[] = $clone;
            }
            return $cloned;
        }
        return $options;
    }

    public function getDdl(
        bool $activeOnly = true,
        string $blankLabel = '',
        string $formatString = '{code} - {name}',
        int $selectedId = 0
    ): array {
        $htmlKey = ($activeOnly ? 'active' : 'all') . '|' . $blankLabel . '|' . $formatString . '|' . $selectedId;
        $dataKey = ($activeOnly ? 'active' : 'all') . '|' . $blankLabel . '|' . $formatString;
        $this->getOrBuildOptions($dataKey, function () use ($activeOnly, $blankLabel, $formatString) {
            return $this->getHtmlOptions($activeOnly, $blankLabel, $formatString);
        });
        $options = self::getOptionCacheState()[$dataKey] ?? [];
        return $this->getOrRenderHtml($htmlKey, $options, $selectedId);
    }

    public function getTeamSelect(
        string $name = 'team_id',
        bool $activeOnly = true,
        string $blankLabel = '',
        string $formatString = '{code} - {name}',
        string $cssClass = 'form-control',
        int $selectedId = 0
    ): HtmlSelect {
        $select = new HtmlSelect($name);
        $select->setClass($cssClass);
        foreach ($this->getHtmlOptions($activeOnly, $blankLabel, $formatString, $selectedId) as $option) {
            $select->addOption($option);
        }
        return $select;
    }

    // ─── CRUD (invalidates cache) ──────────────────────────────────

    public function create(array $data): int
    {
        $id = $this->repo->save($data);
        self::invalidateAllCaches();
        return $id;
    }

    public function update(int $id, array $data): void
    {
        $this->repo->update($id, $data);
        self::invalidateAllCaches();
    }

    public function delete(int $id): void
    {
        $this->repo->delete($id);
        self::invalidateAllCaches();
    }

    /**
     * Soft-delete: clear is_active, keeping positions that reference this row.
     *
     * @param int $id
     *
     * @since 1.0.0
     */
    public function deactivate(int $id): void
    {
        $this->repo->deactivate($id);
        self::invalidateAllCaches();
    }

    public static function invalidateAllCaches(): void
    {
        self::$entityCache = null;
        self::invalidateCache();
    }

    // ─── Hook Response Methods ─────────────────────────────────────

    public function hookGetTeams(array &$data, $opts = null): array
    {
        $activeOnly = $data['active_only'] ?? true;
        $entities = $this->getEntities($activeOnly);
        $result = [];
        foreach ($entities as $entity) {
            $result[] = $entity->toArray();
        }
        return $result;
    }

    public function hookGetTeamDDL(array &$data, $opts = null): array
    {
        return $this->getDdl(
            $data['active_only'] ?? true,
            $data['blank_label'] ?? '',
            $data['format'] ?? '{code} - {name}',
            $data['selected_id'] ?? 0
        );
    }

    public function hookGetTeamHtmlOptions(array &$data, $opts = null): array
    {
        return $this->getHtmlOptions(
            $data['active_only'] ?? true,
            $data['blank_label'] ?? '',
            $data['format'] ?? '{code} - {name}',
            $data['selected_id'] ?? 0
        );
    }
}
