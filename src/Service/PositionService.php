<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Service;

use ksfraser\FrontAccounting\HRM\Repository\PositionRepository;
use ksfraser\FrontAccounting\HRM\Repository\TeamRepository;
use ksfraser\FrontAccounting\HRM\Repository\RoleRepository;
use ksfraser\FrontAccounting\HRM\Entity\Position;
use Ksfraser\HTML\Elements\HtmlOption;
use Ksfraser\HTML\Elements\HtmlSelect;
use Ksfraser\HTML\Traits\DdlCacheTrait;

/**
 * PositionService — DDL caching + hooks for position reference data.
 *
 * @see BR-006 (Cross-Module DDL Caching)
 *
 * @since 1.0.0
 */
class PositionService
{
    use DdlCacheTrait;

    private PositionRepository $positionRepo;
    private TeamRepository $teamRepo;
    private RoleRepository $roleRepo;

    /** @var array[]|null Entity cache */
    private static ?array $entityCache = null;

    public function __construct(
        ?PositionRepository $positionRepo = null,
        ?TeamRepository $teamRepo = null,
        ?RoleRepository $roleRepo = null
    ) {
        $this->positionRepo = $positionRepo ?? new PositionRepository();
        $this->teamRepo = $teamRepo ?? new TeamRepository();
        $this->roleRepo = $roleRepo ?? new RoleRepository();
    }

    // ─── Entity Access ─────────────────────────────────────────────

    public function getEntities(bool $activeOnly = true): array
    {
        $key = $activeOnly ? 'active' : 'all';
        if (self::$entityCache !== null && isset(self::$entityCache[$key])) {
            return self::$entityCache[$key];
        }
        $entities = $this->positionRepo->findActive();
        self::$entityCache[$key] = $entities;
        return $entities;
    }

    public function listAll(): array
    {
        return $this->positionRepo->findActive();
    }

    public function getById(int $id): array
    {
        $entity = $this->positionRepo->findById($id);
        return $entity ? $entity->toArray() : [];
    }

    public function getTeamsForDepartment(int $departmentId): array
    {
        return $this->teamRepo->findByDepartment($departmentId);
    }

    public function getRolesForDepartment(int $departmentId): array
    {
        return $this->roleRepo->findByDepartment($departmentId);
    }

    // ─── DDL (via DdlCacheTrait) ───────────────────────────────────

    public function getHtmlOptions(
        bool $activeOnly = true,
        string $blankLabel = '',
        string $formatString = '{code}',
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
                    [$entity->getPositionCode(), $entity->getPositionCode(), (string)$entity->getPositionId()],
                    $formatString
                );
                $opts[] = new HtmlOption((string)$entity->getPositionId(), $text);
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
        string $formatString = '{code}',
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

    // ─── CRUD (invalidates cache) ──────────────────────────────────

    public function create(array $data): int
    {
        $id = $this->positionRepo->save($data);
        self::invalidateAllCaches();
        return $id;
    }

    public function update(int $id, array $data): void
    {
        $this->positionRepo->update($id, $data);
        self::invalidateAllCaches();
    }

    public static function invalidateAllCaches(): void
    {
        self::$entityCache = null;
        self::invalidateCache();
    }

    // ─── Hook Response Methods ─────────────────────────────────────

    public function hookGetPositions(array &$data, $opts = null): array
    {
        $entities = $this->getEntities($data['active_only'] ?? true);
        $result = [];
        foreach ($entities as $entity) {
            $result[] = $entity->toArray();
        }
        return $result;
    }

    public function hookGetPositionDDL(array &$data, $opts = null): array
    {
        return $this->getDdl(
            $data['active_only'] ?? true,
            $data['blank_label'] ?? '',
            $data['format'] ?? '{code}',
            $data['selected_id'] ?? 0
        );
    }

    public function hookGetPositionHtmlOptions(array &$data, $opts = null): array
    {
        return $this->getHtmlOptions(
            $data['active_only'] ?? true,
            $data['blank_label'] ?? '',
            $data['format'] ?? '{code}',
            $data['selected_id'] ?? 0
        );
    }
}
