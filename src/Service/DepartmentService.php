<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Service;

use ksfraser\FrontAccounting\HRM\Repository\DepartmentRepository;
use ksfraser\FrontAccounting\HRM\Entity\Department;
use Ksfraser\HTML\Elements\HtmlOption;
use Ksfraser\HTML\Elements\HtmlSelect;
use Ksfraser\HTML\Traits\DdlCacheTrait;

/**
 * SRP class that owns all department UI generation and caching.
 *
 * This class builds HTML package objects (HtmlOption, HtmlSelect) and caches
 * them. Consumers call hooks and get pre-rendered HTML or serializable objects.
 *
 * Uses DdlCacheTrait for the three-layer cache (option, HTML, serialization).
 * Entity cache is department-specific (not generic) so stays here.
 *
 * Hook Contract (3 hooks)
 * =======================
 *
 * - 'getDepartments'           → Department[] entity arrays
 * - 'getDepartmentDDL'         → pre-rendered <option> HTML strings
 * - 'getDepartmentHtmlOptions' → HtmlOption[] serializable objects
 *
 * Blank Option & Mandatory Validation
 * ====================================
 *
 * When $blankLabel is provided, the blank option has value="". The
 * consumer's <select> SHOULD have a data-required="1" attribute and
 * the page SHOULD include JS validation.
 *
 * @see BR-006 (Cross-Module DDL Caching)
 * @see FR-006-001 (Three-Layer Cache Architecture)
 * @see FR-006-002 (Option Cache Key Design)
 * @see FR-006-004 (Cache Invalidation)
 * @see FR-006-005 (Hook Contract)
 * @see FR-006-006 (Blank Option & Mandatory Validation)
 *
 * @since 1.0.0
 */
class DepartmentService
{
    use DdlCacheTrait;

    /** @var DepartmentRepository */
    private DepartmentRepository $repo;

    /** @var Department[][]|null Static entity cache (request-scoped, department-specific) */
    private static ?array $entityCache = null;

    /** @var string Hook name for entity retrieval */
    public const HOOK_GET_DEPARTMENTS = 'getDepartments';

    /** @var string Hook name for pre-rendered DDL */
    public const HOOK_GET_DEPARTMENT_DDL = 'getDepartmentDDL';

    /** @var string Hook name for serializable HtmlOption objects */
    public const HOOK_GET_HTML_OPTIONS = 'getDepartmentHtmlOptions';

    public function __construct(?DepartmentRepository $repo = null)
    {
        $this->repo = $repo ?? new DepartmentRepository();
    }

    // ─── Entity Access (cached) ─────────────────────────────────────

    /**
     * Get active departments (cached).
     *
     * @param bool $activeOnly Filter to active departments only (default: true)
     * @return Department[]
     * @since 1.0.0
     */
    public function getDepartments(bool $activeOnly = true): array
    {
        $cacheKey = $activeOnly ? 'active' : 'all';

        if (self::$entityCache !== null && isset(self::$entityCache[$cacheKey])) {
            return self::$entityCache[$cacheKey];
        }

        $departments = $activeOnly
            ? $this->repo->findActive()
            : $this->repo->findAll();

        self::$entityCache[$cacheKey] = $departments;

        return $departments;
    }

    /**
     * Get a single department by ID (not cached — infrequent).
     *
     * @param int $id Department ID
     * @return Department|null
     * @since 1.0.0
     */
    public function getById(int $id): ?Department
    {
        return $this->repo->findById($id);
    }

    // ─── Option Cache (via DdlCacheTrait) ───────────────────────────

    /**
     * Build and cache HtmlOption objects for department DDL.
     *
     * Returns HtmlOption[] that can be serialized across modules.
     * Cache key excludes selectedId — selection is a render concern.
     *
     * @param bool   $activeOnly   Filter to active departments only
     * @param string $blankLabel   Optional blank option label
     * @param string $formatString Format for option text: '{code}', '{name}', '{id}'
     * @param int    $selectedId   Department ID to mark as selected (NOT cached)
     * @return HtmlOption[] Serializable option objects
     * @since 1.0.0
     */
    public function getHtmlOptions(
        bool $activeOnly = true,
        string $blankLabel = '',
        string $formatString = '{code} - {name}',
        int $selectedId = 0
    ): array {
        $dataKey = ($activeOnly ? 'active' : 'all') . '|' . $blankLabel . '|' . $formatString;

        $options = $this->getOrBuildOptions($dataKey, function () use ($activeOnly, $blankLabel, $formatString) {
            $departments = $this->getDepartments($activeOnly);
            $opts = [];

            if ($blankLabel !== '') {
                $opts[] = new HtmlOption('', $blankLabel);
            }

            foreach ($departments as $dept) {
                $text = str_replace(
                    ['{code}', '{name}', '{id}'],
                    [
                        $dept->getDepartmentCode() ?? '',
                        $dept->getDepartmentName(),
                        (string)$dept->getDepartmentId(),
                    ],
                    $formatString
                );
                $opts[] = new HtmlOption((string)$dept->getDepartmentId(), $text);
            }

            return $opts;
        });

        // Apply selected state (NOT cached — per-request rendering)
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

    /**
     * Get the raw option cache key for a given parameter set.
     *
     * @param bool   $activeOnly
     * @param string $blankLabel
     * @param string $formatString
     * @return string Cache key
     * @since 1.0.0
     */
    public function getOptionCacheKey(
        bool $activeOnly = true,
        string $blankLabel = '',
        string $formatString = '{code} - {name}'
    ): string {
        return ($activeOnly ? 'active' : 'all') . '|' . $blankLabel . '|' . $formatString;
    }

    // ─── Pre-rendered HTML (via DdlCacheTrait) ──────────────────────

    /**
     * Get pre-rendered <option> HTML strings for department DDL.
     *
     * Derived from the option cache. The htmlCache key DOES include
     * selectedId since the rendered output differs per selection.
     *
     * @param bool   $activeOnly   Filter to active departments only
     * @param string $blankLabel   Optional blank option label
     * @param string $formatString Format for option text
     * @param int    $selectedId   Department ID to mark as selected
     * @return string[] Pre-rendered <option> HTML strings
     * @since 1.0.0
     */
    public function getDepartmentDDL(
        bool $activeOnly = true,
        string $blankLabel = '',
        string $formatString = '{code} - {name}',
        int $selectedId = 0
    ): array {
        $htmlKey = ($activeOnly ? 'active' : 'all') . '|' . $blankLabel . '|' . $formatString . '|' . $selectedId;

        // Ensure option cache is populated
        $dataKey = ($activeOnly ? 'active' : 'all') . '|' . $blankLabel . '|' . $formatString;
        $this->getOrBuildOptions($dataKey, function () use ($activeOnly, $blankLabel, $formatString) {
            return $this->getHtmlOptions($activeOnly, $blankLabel, $formatString);
        });

        $options = self::getOptionCacheState()[$dataKey] ?? [];

        return $this->getOrRenderHtml($htmlKey, $options, $selectedId);
    }

    /**
     * Get a complete HtmlSelect element for department DDL.
     *
     * @param string $name        Select element name attribute
     * @param bool   $activeOnly  Filter to active departments only
     * @param string $blankLabel  Optional blank option label
     * @param string $formatString Format for option text
     * @param string $cssClass    Optional CSS class
     * @param int    $selectedId  Department ID to mark as selected
     * @return HtmlSelect Configured select element
     * @since 1.0.0
     */
    public function getDepartmentSelect(
        string $name = 'department_id',
        bool $activeOnly = true,
        string $blankLabel = '',
        string $formatString = '{code} - {name}',
        string $cssClass = 'form-control',
        int $selectedId = 0
    ): HtmlSelect {
        $select = new HtmlSelect($name);
        $select->setClass($cssClass);

        $options = $this->getHtmlOptions($activeOnly, $blankLabel, $formatString, $selectedId);
        foreach ($options as $option) {
            $select->addOption($option);
        }

        return $select;
    }

    // ─── Cache Management ───────────────────────────────────────────

    /**
     * Create a new department and invalidate all caches.
     *
     * @param array $data Department data
     * @return int New department ID
     * @since 1.0.0
     */
    public function create(array $data): int
    {
        $id = $this->repo->save($data);
        self::invalidateAllCaches();
        return $id;
    }

    /**
     * Update a department and invalidate all caches.
     *
     * @param int   $id   Department ID
     * @param array $data Fields to update
     * @since 1.0.0
     */
    public function update(int $id, array $data): void
    {
        $this->repo->update($id, $data);
        self::invalidateAllCaches();
    }

    /**
     * Delete a department and invalidate all caches.
     *
     * @param int $id Department ID
     * @since 1.0.0
     */
    public function delete(int $id): void
    {
        $this->repo->delete($id);
        self::invalidateAllCaches();
    }

    /**
     * Invalidate all caches (entity + DDL trait caches).
     *
     * @since 1.0.0
     */
    public static function invalidateAllCaches(): void
    {
        self::$entityCache = null;
        self::invalidateCache(); // DdlCacheTrait
    }

    // ─── Hook Response Methods ──────────────────────────────────────

    /**
     * Respond to 'getDepartments' hook.
     *
     * @param array $data Hook payload (by reference)
     * @param array|null $opts Hook options
     * @return array Department entity arrays
     * @since 1.0.0
     */
    public function hookGetDepartments(array &$data, $opts = null): array
    {
        $activeOnly = $data['active_only'] ?? true;
        $entities = $this->getDepartments($activeOnly);

        $result = [];
        foreach ($entities as $dept) {
            $result[] = $dept->toArray();
        }
        return $result;
    }

    /**
     * Respond to 'getDepartmentDDL' hook.
     *
     * @param array $data Hook payload (by reference)
     * @param array|null $opts Hook options
     * @return array Pre-rendered <option> HTML strings
     * @since 1.0.0
     */
    public function hookGetDepartmentDDL(array &$data, $opts = null): array
    {
        $activeOnly   = $data['active_only'] ?? true;
        $blankLabel   = $data['blank_label'] ?? '';
        $formatString = $data['format'] ?? '{code} - {name}';
        $selectedId   = $data['selected_id'] ?? 0;

        return $this->getDepartmentDDL($activeOnly, $blankLabel, $formatString, $selectedId);
    }

    /**
     * Respond to 'getDepartmentHtmlOptions' hook.
     *
     * @param array $data Hook payload (by reference)
     * @param array|null $opts Hook options
     * @return HtmlOption[] Serializable option objects
     * @since 1.0.0
     */
    public function hookGetHtmlOptions(array &$data, $opts = null): array
    {
        $activeOnly   = $data['active_only'] ?? true;
        $blankLabel   = $data['blank_label'] ?? '';
        $formatString = $data['format'] ?? '{code} - {name}';
        $selectedId   = $data['selected_id'] ?? 0;

        return $this->getHtmlOptions($activeOnly, $blankLabel, $formatString, $selectedId);
    }
}
