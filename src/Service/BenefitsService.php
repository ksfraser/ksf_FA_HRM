<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Service;

use ksfraser\FrontAccounting\HRM\Repository\BenefitRepository;
use ksfraser\FrontAccounting\HRM\Entity\Benefit;
use Ksfraser\HTML\Elements\HtmlOption;
use Ksfraser\HTML\Elements\HtmlSelect;
use Ksfraser\HTML\Traits\DdlCacheTrait;

/**
 * BenefitsService — DDL caching + hooks for benefit reference data.
 *
 * @see BR-006 (Cross-Module DDL Caching)
 *
 * @since 1.0.0
 */
class BenefitsService
{
    use DdlCacheTrait;

    private BenefitRepository $benefitRepo;

    /** @var array[]|null Entity cache */
    private static ?array $entityCache = null;

    public function __construct(?BenefitRepository $repo = null)
    {
        $this->benefitRepo = $repo ?? new BenefitRepository();
    }

    // ─── Entity Access ─────────────────────────────────────────────

    public function getEntities(bool $activeOnly = true): array
    {
        $key = $activeOnly ? 'active' : 'all';
        if (self::$entityCache !== null && isset(self::$entityCache[$key])) {
            return self::$entityCache[$key];
        }
        $entities = $this->benefitRepo->findAll($activeOnly);
        self::$entityCache[$key] = $entities;
        return $entities;
    }

    public function listAll(bool $activeOnly = false): array
    {
        return $this->benefitRepo->findAll($activeOnly);
    }

    public function getById(int $id): array
    {
        $entity = $this->benefitRepo->findById($id);
        return $entity ? $entity->toArray() : [];
    }

    public function getEmployeeBenefits(int $personId): array
    {
        return $this->benefitRepo->findEmployeeBenefits($personId);
    }

    public function assignBenefit(array $data): int
    {
        return $this->benefitRepo->saveEmployeeBenefit($data);
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
                    [$entity->getBenefitCode(), $entity->getBenefitName(), (string)$entity->getBenefitId()],
                    $formatString
                );
                $opts[] = new HtmlOption((string)$entity->getBenefitId(), $text);
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

    public function getBenefitSelect(
        string $name = 'benefit_id',
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
        $id = $this->benefitRepo->save($data);
        self::invalidateAllCaches();
        return $id;
    }

    public function deactivate(int $id): void
    {
        $this->benefitRepo->deactivate($id);
        self::invalidateAllCaches();
    }

    public static function invalidateAllCaches(): void
    {
        self::$entityCache = null;
        self::invalidateCache();
    }

    // ─── Hook Response Methods ─────────────────────────────────────

    public function hookGetBenefits(array &$data, $opts = null): array
    {
        $entities = $this->getEntities($data['active_only'] ?? true);
        $result = [];
        foreach ($entities as $entity) {
            $result[] = $entity->toArray();
        }
        return $result;
    }

    public function hookGetBenefitDDL(array &$data, $opts = null): array
    {
        return $this->getDdl(
            $data['active_only'] ?? true,
            $data['blank_label'] ?? '',
            $data['format'] ?? '{code} - {name}',
            $data['selected_id'] ?? 0
        );
    }

    public function hookGetBenefitHtmlOptions(array &$data, $opts = null): array
    {
        return $this->getHtmlOptions(
            $data['active_only'] ?? true,
            $data['blank_label'] ?? '',
            $data['format'] ?? '{code} - {name}',
            $data['selected_id'] ?? 0
        );
    }
}
