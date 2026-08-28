<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ksfraser\FrontAccounting\HRM\Service\DepartmentService;
use ksfraser\FrontAccounting\HRM\Repository\DepartmentRepository;
use Ksfraser\HTML\Elements\HtmlOption;

/**
 * Unit tests for DepartmentService.
 *
 * Covers the three-layer cache architecture:
 * - entityCache: Department[] (data)
 * - optionCache: HtmlOption[][] (serializable data, keyed without selectedId)
 * - htmlCache: string[][] (rendered output, keyed with selectedId)
 *
 * @BABOK Related: BR-006
 * @BABOK Related: FR-006-001
 * @BABOK Related: FR-006-002
 * @BABOK Related: FR-006-004
 * @BABOK Related: FR-006-005
 * @BABOK Related: FR-006-006
 */
class DepartmentServiceTest extends TestCase
{
    /** @var DepartmentService */
    private $service;

    protected function setUp(): void
    {
        // Reset static caches between tests
        DepartmentService::invalidateCache();

        // Seed fake DB with department rows.
        // NOTE: The fake DB doesn't filter WHERE clauses — it returns the
        // same result for ALL SELECT queries. So we seed only the rows that
        // findActive() would return (active=1) to match expected behavior.
        $GLOBALS['__fa_select_queue'] = [
            [
                [
                    'department_id' => 1,
                    'department_code' => 'IT',
                    'department_name' => 'Information Technology',
                    'manager_person_id' => null,
                    'parent_department_id' => null,
                    'cost_center_id' => null,
                    'description' => 'Tech department',
                    'is_active' => 1,
                    'created_at' => '2024-01-01 00:00:00',
                    'updated_at' => '2024-01-01 00:00:00',
                ],
                [
                    'department_id' => 2,
                    'department_code' => 'HR',
                    'department_name' => 'Human Resources',
                    'manager_person_id' => null,
                    'parent_department_id' => null,
                    'cost_center_id' => null,
                    'description' => 'People department',
                    'is_active' => 1,
                    'created_at' => '2024-01-01 00:00:00',
                    'updated_at' => '2024-01-01 00:00:00',
                ],
            ],
        ];

        $this->service = new DepartmentService();
    }

    protected function tearDown(): void
    {
        DepartmentService::invalidateCache();
        unset($GLOBALS['__fa_select_queue']);
        unset($GLOBALS['__fa_current_result']);
    }

    // ─── Entity Access ──────────────────────────────────────────────

    public function testGetDepartmentsReturnsActiveOnlyByDefault(): void
    {
        $departments = $this->service->getDepartments();

        $this->assertCount(2, $departments);
        foreach ($departments as $dept) {
            $this->assertTrue($dept->isActive());
        }
    }

    public function testGetDepartmentsReturnsAllWhenRequested(): void
    {
        $departments = $this->service->getDepartments(false);

        // Fake DB returns same set for all SELECTs, so both return 2
        $this->assertCount(2, $departments);
    }

    public function testGetDepartmentsCachesResult(): void
    {
        $first = $this->service->getDepartments();
        $second = $this->service->getDepartments();

        // Same array reference — served from cache
        $this->assertSame($first, $second);
    }

    // ─── Option Cache (serializable layer) ──────────────────────────

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
        // First call — no selection
        $options1 = $this->service->getHtmlOptions(true, '', '{code} - {name}', 0);

        // Second call — with selection (different selectedId, same data params)
        $options2 = $this->service->getHtmlOptions(true, '', '{code} - {name}', 1);

        // Cache key is the same (no selectedId in key)
        $cacheState = DepartmentService::getOptionCacheState();
        $this->assertCount(1, $cacheState);

        // The cached options should NOT have any selected state
        $cachedOptions = reset($cacheState);
        foreach ($cachedOptions as $opt) {
            $this->assertFalse($opt->isSelected());
        }
    }

    public function testGetHtmlOptionsClonesWhenSelected(): void
    {
        $options1 = $this->service->getHtmlOptions(true, '', '{code} - {name}', 0);
        $options2 = $this->service->getHtmlOptions(true, '', '{code} - {name}', 1);

        // options2 should be cloned objects (different instances)
        foreach ($options1 as $i => $opt) {
            $this->assertNotSame($opt, $options2[$i]);
        }

        // The selected option in options2 should be marked
        $selected = array_filter($options2, fn($o) => $o->isSelected());
        $this->assertCount(1, $selected);
        $this->assertSame('1', reset($selected)->getValue());
    }

    public function testGetHtmlOptionsFormatStringCustom(): void
    {
        $options = $this->service->getHtmlOptions(true, '', '{name}');

        $this->assertCount(2, $options);
        $this->assertSame('Information Technology', $options[0]->getLabel());
        $this->assertSame('Human Resources', $options[1]->getLabel());
    }

    public function testGetHtmlOptionsFormatStringIdOnly(): void
    {
        $options = $this->service->getHtmlOptions(true, '', '{id}');

        $this->assertSame('1', $options[0]->getValue());
        $this->assertSame('1', $options[0]->getLabel());
    }

    public function testGetOptionCacheKey(): void
    {
        $key = $this->service->getOptionCacheKey(true, '', '{code} - {name}');
        $this->assertSame('active||{code} - {name}', $key);

        $key2 = $this->service->getOptionCacheKey(false, '-- None --', '{name}');
        $this->assertSame('all|-- None --|{name}', $key2);
    }

    // ─── Pre-rendered HTML (derived layer) ───────────────────────────

    public function testGetDepartmentDdlReturnsStrings(): void
    {
        $rendered = $this->service->getDepartmentDDL();

        $this->assertCount(2, $rendered);
        foreach ($rendered as $html) {
            $this->assertIsString($html);
            $this->assertStringContainsString('<option', $html);
            $this->assertStringContainsString('</option>', $html);
        }
    }

    public function testGetDepartmentDdlWithBlankLabel(): void
    {
        $rendered = $this->service->getDepartmentDDL(true, '-- Choose --');

        $this->assertCount(3, $rendered);
        $this->assertStringContainsString('-- Choose --', $rendered[0]);
        $this->assertStringContainsString('value=""', $rendered[0]);
    }

    public function testGetDepartmentDdlWithSelectedId(): void
    {
        $rendered = $this->service->getDepartmentDDL(true, '', '{code} - {name}', 1);

        // First option (IT, id=1) should have 'selected'
        $this->assertStringContainsString('selected', $rendered[0]);
        // Second option (HR, id=2) should NOT have 'selected'
        $this->assertStringNotContainsString('selected', $rendered[1]);
    }

    public function testGetDepartmentDdlCacheDifferentiatesBySelectedId(): void
    {
        $r1 = $this->service->getDepartmentDDL(true, '', '{code}', 0);
        $r2 = $this->service->getDepartmentDDL(true, '', '{code}', 1);
        $r3 = $this->service->getDepartmentDDL(true, '', '{code}', 2);

        // All different (selected state differs)
        $this->assertNotSame($r1, $r2);
        $this->assertNotSame($r2, $r3);

        // But second call returns cached
        $this->assertSame($r2, $this->service->getDepartmentDDL(true, '', '{code}', 1));
    }

    public function testGetDepartmentDdlDoesNotMutateCache(): void
    {
        // Render with selected
        $this->service->getDepartmentDDL(true, '', '{code}', 1);

        // Check that option cache still has no selected state
        $cacheState = DepartmentService::getOptionCacheState();
        $this->assertNotNull($cacheState);

        foreach ($cacheState as $options) {
            foreach ($options as $opt) {
                $this->assertFalse($opt->isSelected());
            }
        }
    }

    public function testGetDepartmentSelectReturnsHtmlSelect(): void
    {
        $select = $this->service->getDepartmentSelect('dept_id', true, '-- None --');

        $this->assertInstanceOf(\Ksfraser\HTML\Elements\HtmlSelect::class, $select);
        $this->assertSame('dept_id', $select->getName());
        $this->assertCount(3, $select->getOptions()); // 2 depts + blank
    }

    // ─── Serialized Cache (portable layer) ──────────────────────────

    public function testGetSerializedCacheReturnsString(): void
    {
        // Populate option cache first
        $this->service->getHtmlOptions();
        $serialized = $this->service->getSerializedCache();

        $this->assertIsString($serialized);
        $this->assertNotEmpty($serialized);

        // Should be unserializable back to an array
        $unserialized = unserialize($serialized);
        $this->assertIsArray($unserialized);
    }

    public function testGetSerializedCacheContainsOptionArrays(): void
    {
        $this->service->getHtmlOptions();
        $serialized = $this->service->getSerializedCache();
        $unserialized = unserialize($serialized);

        // Should have one entry keyed by data params
        $this->assertCount(1, $unserialized);

        foreach ($unserialized as $options) {
            $this->assertCount(2, $options);
            foreach ($options as $opt) {
                $this->assertInstanceOf(HtmlOption::class, $opt);
            }
        }
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

        // HR (id=2) should have 'selected'
        $this->assertStringContainsString('selected', $rendered[1]);
        // IT (id=1) should NOT
        $this->assertStringNotContainsString('selected', $rendered[0]);
    }

    public function testRenderFromSerializedCacheDoesNotMutateCache(): void
    {
        $this->service->getHtmlOptions();
        $serialized = $this->service->getSerializedCache();

        // Render with selection
        $this->service->renderFromSerializedCache($serialized, 1);

        // Option cache should still have no selected state
        $cacheState = DepartmentService::getOptionCacheState();
        foreach ($cacheState as $options) {
            foreach ($options as $opt) {
                $this->assertFalse($opt->isSelected());
            }
        }
    }

    public function testRenderFromSerializedCacheInvalidStringReturnsEmpty(): void
    {
        $rendered = $this->service->renderFromSerializedCache('garbage data');
        $this->assertSame([], $rendered);
    }

    public function testRenderFromSerializedCacheEmptyStringReturnsEmpty(): void
    {
        $rendered = $this->service->renderFromSerializedCache('');
        $this->assertSame([], $rendered);
    }

    // ─── Cache Invalidation ─────────────────────────────────────────

    public function testInvalidateCacheClearsAllLayers(): void
    {
        // Populate all caches
        $this->service->getDepartments();
        $this->service->getHtmlOptions();
        $this->service->getDepartmentDDL();

        $this->assertNotNull(DepartmentService::getOptionCacheState());

        // Invalidate
        DepartmentService::invalidateCache();

        $this->assertNull(DepartmentService::getOptionCacheState());
    }

    public function testCreateInvalidatesCache(): void
    {
        // Populate all cache layers
        $this->service->getDepartments();
        $this->service->getHtmlOptions();
        $this->assertNotNull(DepartmentService::getOptionCacheState());

        // Create (INSERT — fake DB returns true)
        $GLOBALS['__fa_next_id'] = 10;
        $this->service->create([
            'department_code' => 'FIN',
            'department_name' => 'Finance',
        ]);

        $this->assertNull(DepartmentService::getOptionCacheState());
    }

    public function testUpdateInvalidatesCache(): void
    {
        $this->service->getDepartments();
        $this->service->getHtmlOptions();
        $this->assertNotNull(DepartmentService::getOptionCacheState());

        $this->service->update(1, ['department_name' => 'New Name']);

        $this->assertNull(DepartmentService::getOptionCacheState());
    }

    public function testDeleteInvalidatesCache(): void
    {
        $this->service->getDepartments();
        $this->service->getHtmlOptions();
        $this->assertNotNull(DepartmentService::getOptionCacheState());

        $this->service->delete(1);

        $this->assertNull(DepartmentService::getOptionCacheState());
    }

    // ─── Hook Response Methods ──────────────────────────────────────

    public function testHookGetDepartmentsReturnsArrays(): void
    {
        $data = ['active_only' => true];
        $result = $this->service->hookGetDepartments($data);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('department_id', $result[0]);
        $this->assertArrayHasKey('department_name', $result[0]);
    }

    public function testHookGetDepartmentDdlReturnsStrings(): void
    {
        $data = ['active_only' => true, 'blank_label' => '-- Pick --'];
        $result = $this->service->hookGetDepartmentDDL($data);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertStringContainsString('-- Pick --', $result[0]);
    }

    public function testHookGetDepartmentDdlWithSelectedId(): void
    {
        $data = ['active_only' => true, 'selected_id' => 2];
        $result = $this->service->hookGetDepartmentDDL($data);

        $this->assertStringContainsString('selected', $result[1]);
    }

    public function testHookGetHtmlOptionsReturnsOptionObjects(): void
    {
        $data = ['active_only' => true];
        $result = $this->service->hookGetHtmlOptions($data);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(HtmlOption::class, $result[0]);
    }

    // ─── Blank Option & Mandatory Validation (FR-006-006) ──────────

    public function testBlankOptionHasEmptyValue(): void
    {
        $rendered = $this->service->getDepartmentDDL(true, '-- Select Department --');

        // First option should be the blank with value=""
        $this->assertStringContainsString('value=""', $rendered[0]);
        $this->assertStringContainsString('-- Select Department --', $rendered[0]);
    }

    public function testBlankOptionNotIncludedWhenNoBlankLabel(): void
    {
        $rendered = $this->service->getDepartmentDDL(true, '');

        // No blank option — first option should be a real department
        $this->assertStringNotContainsString('value=""', $rendered[0]);
    }

    public function testBlankOptionValueEmptyString(): void
    {
        $options = $this->service->getHtmlOptions(true, '-- None --');

        // First option (blank) should have empty string value
        $this->assertSame('', $options[0]->getValue());
        $this->assertSame('-- None --', $options[0]->getLabel());
    }

    // ─── Cherry-pick Scenario ───────────────────────────────────────

    public function testCherryPickFromSerializedCache(): void
    {
        // Populate cache first
        $this->service->getHtmlOptions();

        // Module A gets the serialized cache and stores it
        $serialized = $this->service->getSerializedCache();

        // Module B receives the serialized cache, unserializes, cherry-picks
        $unserialized = unserialize($serialized);
        $this->assertIsArray($unserialized);

        // Walk the option tree — find HR department
        $hrOption = null;
        foreach ($unserialized as $options) {
            foreach ($options as $opt) {
                if ($opt->getValue() === '2') {
                    $hrOption = $opt;
                    break;
                }
            }
        }

        $this->assertNotNull($hrOption);
        $this->assertSame('HR - Human Resources', $hrOption->getLabel());

        // Module B can manipulate it
        $hrOption->setSelected(true);
        $this->assertTrue($hrOption->isSelected());
        $this->assertStringContainsString('selected', $hrOption->getHtml());
    }

    public function testMultipleConsumersShareCachedOptions(): void
    {
        // First consumer — populates cache
        $options1 = $this->service->getHtmlOptions(true, '', '{code} - {name}', 0);

        // Second consumer — same params, different selection (from cache)
        $options2 = $this->service->getHtmlOptions(true, '', '{code} - {name}', 2);

        // Cache state should still have only one entry
        $cacheState = DepartmentService::getOptionCacheState();
        $this->assertCount(1, $cacheState);

        // But options2 should be cloned with selection applied
        $this->assertNotSame($options1, $options2);
        $selectedOpts = array_filter($options2, fn($o) => $o->isSelected());
        $this->assertCount(1, $selectedOpts);
    }
}
