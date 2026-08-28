<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ksfraser\FrontAccounting\HRM\Service\RoleDictionaryService;
use Ksfraser\HTML\Elements\HtmlOption;

/**
 * Unit tests for RoleDictionaryService.
 *
 * Read-only entity (no CRUD) — cache invalidation tests are limited to invalidateCache().
 *
 * @BABOK Related: BR-006
 * @BABOK Related: FR-006-005
 */
class RoleDictionaryServiceTest extends TestCase
{
    private RoleDictionaryService $service;

    protected function setUp(): void
    {
        RoleDictionaryService::invalidateCache();

        $GLOBALS['__fa_select_queue'] = [
            [
                [
                    'role_dict_id' => 1,
                    'role_name' => 'Developer',
                    'description' => 'Writes code',
                    'is_active' => 1,
                    'created_at' => '2024-01-01 00:00:00',
                ],
                [
                    'role_dict_id' => 2,
                    'role_name' => 'Manager',
                    'description' => 'Leads team',
                    'is_active' => 1,
                    'created_at' => '2024-01-01 00:00:00',
                ],
            ],
        ];

        $this->service = new RoleDictionaryService();
    }

    protected function tearDown(): void
    {
        RoleDictionaryService::invalidateCache();
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
        $this->service->getHtmlOptions(true, '', '{name}', 0);
        $this->service->getHtmlOptions(true, '', '{name}', 1);

        $cacheState = RoleDictionaryService::getOptionCacheState();
        $this->assertCount(1, $cacheState);

        $cachedOptions = reset($cacheState);
        foreach ($cachedOptions as $opt) {
            $this->assertFalse($opt->isSelected());
        }
    }

    public function testGetHtmlOptionsClonesWhenSelected(): void
    {
        $options1 = $this->service->getHtmlOptions(true, '', '{name}', 0);
        $options2 = $this->service->getHtmlOptions(true, '', '{name}', 1);

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
        $rendered = $this->service->getDdl(true, '', '{name}', 1);
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

    public function testRenderFromSerializedCacheWithSelectedId(): void
    {
        $this->service->getHtmlOptions();
        $serialized = $this->service->getSerializedCache();
        $rendered = $this->service->renderFromSerializedCache($serialized, 2);
        $this->assertStringContainsString('selected', $rendered[1]);
        $this->assertStringNotContainsString('selected', $rendered[0]);
    }

    // ─── Cache Invalidation ─────────────────────────────────────────

    public function testInvalidateCacheClearsAllLayers(): void
    {
        $this->service->getEntities();
        $this->service->getHtmlOptions();
        RoleDictionaryService::invalidateCache();
        $this->assertNull(RoleDictionaryService::getOptionCacheState());
    }

    // ─── Hook Response Methods ──────────────────────────────────────

    public function testHookGetRoleDictionaryReturnsArrays(): void
    {
        $data = ['active_only' => true];
        $result = $this->service->hookGetRoleDictionary($data);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('role_dict_id', $result[0]);
        $this->assertArrayHasKey('role_name', $result[0]);
    }

    public function testHookGetRoleDictionaryDdlReturnsStrings(): void
    {
        $data = ['active_only' => true, 'blank_label' => '-- Pick --'];
        $result = $this->service->hookGetRoleDictionaryDDL($data);
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertStringContainsString('-- Pick --', $result[0]);
    }

    public function testHookGetRoleDictionaryHtmlOptionsReturnsOptionObjects(): void
    {
        $data = ['active_only' => true];
        $result = $this->service->hookGetRoleDictionaryHtmlOptions($data);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(HtmlOption::class, $result[0]);
    }

    // ─── Blank Option & Mandatory Validation (FR-006-006) ──────────

    public function testBlankOptionHasEmptyValue(): void
    {
        $rendered = $this->service->getDdl(true, '-- Select Role --');
        $this->assertStringContainsString('value=""', $rendered[0]);
        $this->assertStringContainsString('-- Select Role --', $rendered[0]);
    }

    public function testBlankOptionNotIncludedWhenNoBlankLabel(): void
    {
        $rendered = $this->service->getDdl(true, '');
        $this->assertStringNotContainsString('value=""', $rendered[0]);
    }
}
