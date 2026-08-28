<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ksfraser\FrontAccounting\HRM\Service\PositionService;
use ksfraser\FrontAccounting\HRM\Repository\PositionRepository;
use Ksfraser\HTML\Elements\HtmlOption;

/**
 * Unit tests for PositionService.
 *
 * @BABOK Related: BR-006
 * @BABOK Related: FR-006-005
 */
class PositionServiceTest extends TestCase
{
    private PositionService $service;

    protected function setUp(): void
    {
        PositionService::invalidateCache();

        $GLOBALS['__fa_select_queue'] = [
            [
                [
                    'position_id' => 1,
                    'position_code' => 'IT-DEV-001',
                    'department_id' => 1,
                    'team_id' => 1,
                    'role_id' => 1,
                    'position_number' => 1,
                    'description' => null,
                    'is_active' => 1,
                    'created_at' => '2024-01-01 00:00:00',
                    'updated_at' => '2024-01-01 00:00:00',
                    'department_code' => 'IT',
                    'team_code' => 'IT-DEV',
                    'role_name' => 'Developer',
                ],
                [
                    'position_id' => 2,
                    'position_code' => 'IT-OPS-001',
                    'department_id' => 1,
                    'team_id' => 2,
                    'role_id' => 2,
                    'position_number' => 1,
                    'description' => null,
                    'is_active' => 1,
                    'created_at' => '2024-01-01 00:00:00',
                    'updated_at' => '2024-01-01 00:00:00',
                    'department_code' => 'IT',
                    'team_code' => 'IT-OPS',
                    'role_name' => 'Manager',
                ],
            ],
        ];

        $this->service = new PositionService();
    }

    protected function tearDown(): void
    {
        PositionService::invalidateCache();
        unset($GLOBALS['__fa_select_queue']);
        unset($GLOBALS['__fa_current_result']);
    }

    // ─── Entity Access ──────────────────────────────────────────────

    public function testGetEntitiesReturnsActiveOnlyByDefault(): void
    {
        $entities = $this->service->getEntities();
        $this->assertCount(2, $entities);
        foreach ($entities as $e) {
            $this->assertTrue($e->isActive());
        }
    }

    public function testGetEntitiesCachesResult(): void
    {
        $first = $this->service->getEntities();
        $second = $this->service->getEntities();
        $this->assertSame($first, $second);
    }

    // ─── Option Cache ───────────────────────────────────────────────

    public function testGetHtmlOptionsReturnsHtmlOptionArray(): void
    {
        $options = $this->service->getHtmlOptions();
        $this->assertCount(2, $options);
        foreach ($options as $opt) {
            $this->assertInstanceOf(HtmlOption::class, $opt);
        }
    }

    public function testGetHtmlOptionsIncludesBlankLabel(): void
    {
        $options = $this->service->getHtmlOptions(true, '-- Select --');
        $this->assertCount(3, $options);
        $this->assertSame('', $options[0]->getValue());
        $this->assertSame('-- Select --', $options[0]->getLabel());
    }

    public function testGetHtmlOptionsCacheExcludesSelectedId(): void
    {
        $this->service->getHtmlOptions(true, '', '{code}', 0);
        $this->service->getHtmlOptions(true, '', '{code}', 1);

        $cacheState = PositionService::getOptionCacheState();
        $this->assertCount(1, $cacheState);

        $cachedOptions = reset($cacheState);
        foreach ($cachedOptions as $opt) {
            $this->assertFalse($opt->isSelected());
        }
    }

    public function testGetHtmlOptionsClonesWhenSelected(): void
    {
        $options1 = $this->service->getHtmlOptions(true, '', '{code}', 0);
        $options2 = $this->service->getHtmlOptions(true, '', '{code}', 1);

        foreach ($options1 as $i => $opt) {
            $this->assertNotSame($opt, $options2[$i]);
        }

        $selected = array_filter($options2, fn($o) => $o->isSelected());
        $this->assertCount(1, $selected);
        $this->assertSame('1', reset($selected)->getValue());
    }

    // ─── Pre-rendered HTML DDL ──────────────────────────────────────

    public function testGetDdlReturnsStrings(): void
    {
        $rendered = $this->service->getDdl();
        $this->assertCount(2, $rendered);
        foreach ($rendered as $html) {
            $this->assertIsString($html);
            $this->assertStringContainsString('<option', $html);
        }
    }

    public function testGetDdlWithSelectedId(): void
    {
        $rendered = $this->service->getDdl(true, '', '{code}', 1);
        $this->assertStringContainsString('selected', $rendered[0]);
        $this->assertStringNotContainsString('selected', $rendered[1]);
    }

    // ─── Serialized Cache ───────────────────────────────────────────

    public function testGetSerializedCacheReturnsString(): void
    {
        $this->service->getHtmlOptions();
        $serialized = $this->service->getSerializedCache();
        $this->assertIsString($serialized);
        $unserialized = unserialize($serialized);
        $this->assertIsArray($unserialized);
    }

    public function testRenderFromSerializedCacheReturnsStrings(): void
    {
        $this->service->getHtmlOptions();
        $serialized = $this->service->getSerializedCache();
        $rendered = $this->service->renderFromSerializedCache($serialized);
        $this->assertCount(2, $rendered);
        $this->assertStringContainsString('<option', $rendered[0]);
    }

    // ─── Cache Invalidation ─────────────────────────────────────────

    public function testInvalidateCacheClearsAllLayers(): void
    {
        $this->service->getEntities();
        $this->service->getHtmlOptions();
        PositionService::invalidateCache();
        $this->assertNull(PositionService::getOptionCacheState());
    }

    public function testCreateInvalidatesCache(): void
    {
        $this->service->getEntities();
        $this->service->getHtmlOptions();

        $mockRepo = $this->createMock(PositionRepository::class);
        $mockRepo->method('save')->willReturn(99);

        $serviceWithMock = new PositionService($mockRepo);
        $serviceWithMock->create([
            'position_code' => 'HR-001',
            'department_id' => 2,
            'role_id' => 1,
        ]);
        $this->assertNull(PositionService::getOptionCacheState());
    }

    public function testUpdateInvalidatesCache(): void
    {
        $this->service->getEntities();
        $this->service->getHtmlOptions();
        $this->service->update(1, ['description' => 'Updated']);
        $this->assertNull(PositionService::getOptionCacheState());
    }

    // ─── Hook Response Methods ──────────────────────────────────────

    public function testHookGetPositionsReturnsArrays(): void
    {
        $data = ['active_only' => true];
        $result = $this->service->hookGetPositions($data);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('position_id', $result[0]);
        $this->assertArrayHasKey('position_code', $result[0]);
    }

    public function testHookGetPositionDdlReturnsStrings(): void
    {
        $data = ['active_only' => true, 'blank_label' => '-- Pick --'];
        $result = $this->service->hookGetPositionDDL($data);
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertStringContainsString('-- Pick --', $result[0]);
    }

    public function testHookGetPositionHtmlOptionsReturnsOptionObjects(): void
    {
        $data = ['active_only' => true];
        $result = $this->service->hookGetPositionHtmlOptions($data);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(HtmlOption::class, $result[0]);
    }

    // ─── Blank Option & Mandatory Validation (FR-006-006) ──────────

    public function testBlankOptionHasEmptyValue(): void
    {
        $rendered = $this->service->getDdl(true, '-- Select Position --');
        $this->assertStringContainsString('value=""', $rendered[0]);
        $this->assertStringContainsString('-- Select Position --', $rendered[0]);
    }

    public function testBlankOptionNotIncludedWhenNoBlankLabel(): void
    {
        $rendered = $this->service->getDdl(true, '');
        $this->assertStringNotContainsString('value=""', $rendered[0]);
    }
}
