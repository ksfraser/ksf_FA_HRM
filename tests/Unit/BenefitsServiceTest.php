<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ksfraser\FrontAccounting\HRM\Service\BenefitsService;
use Ksfraser\HTML\Elements\HtmlOption;

/**
 * Unit tests for BenefitsService.
 *
 * @BABOK Related: BR-006
 * @BABOK Related: FR-006-005
 */
class BenefitsServiceTest extends TestCase
{
    private BenefitsService $service;

    protected function setUp(): void
    {
        BenefitsService::invalidateCache();

        $GLOBALS['__fa_select_queue'] = [
            [
                [
                    'benefit_id' => 1,
                    'benefit_code' => 'HEALTH',
                    'benefit_name' => 'Health Insurance',
                    'benefit_type' => 'Insurance',
                    'employer_rate' => 0.0,
                    'employee_rate' => 50.0,
                    'fixed_amount' => 0.0,
                    'is_percentage_based' => 0,
                    'calculation_period' => 'Monthly',
                    'gl_code_expense' => null,
                    'gl_code_liability' => null,
                    'provider' => 'BlueCross',
                    'is_mandatory' => 0,
                    'is_tax_deductible' => 1,
                    'description' => null,
                    'is_active' => 1,
                    'created_at' => '2024-01-01 00:00:00',
                    'updated_at' => '2024-01-01 00:00:00',
                ],
                [
                    'benefit_id' => 2,
                    'benefit_code' => 'PENSION',
                    'benefit_name' => 'Pension Plan',
                    'benefit_type' => 'Retirement',
                    'employer_rate' => 5.0,
                    'employee_rate' => 3.0,
                    'fixed_amount' => 0.0,
                    'is_percentage_based' => 1,
                    'calculation_period' => 'Monthly',
                    'gl_code_expense' => null,
                    'gl_code_liability' => null,
                    'provider' => 'Fidelity',
                    'is_mandatory' => 1,
                    'is_tax_deductible' => 1,
                    'description' => null,
                    'is_active' => 1,
                    'created_at' => '2024-01-01 00:00:00',
                    'updated_at' => '2024-01-01 00:00:00',
                ],
            ],
        ];

        $this->service = new BenefitsService();
    }

    protected function tearDown(): void
    {
        BenefitsService::invalidateCache();
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
        $this->service->getHtmlOptions(true, '', '{code} - {name}', 0);
        $this->service->getHtmlOptions(true, '', '{code} - {name}', 1);

        $cacheState = BenefitsService::getOptionCacheState();
        $this->assertCount(1, $cacheState);

        $cachedOptions = reset($cacheState);
        foreach ($cachedOptions as $opt) {
            $this->assertFalse($opt->isSelected());
        }
    }

    public function testGetHtmlOptionsClonesWhenSelected(): void
    {
        $options1 = $this->service->getHtmlOptions(true, '', '{code} - {name}', 0);
        $options2 = $this->service->getHtmlOptions(true, '', '{code} - {name}', 1);

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
        $rendered = $this->service->getDdl(true, '', '{code} - {name}', 1);
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
        BenefitsService::invalidateCache();
        $this->assertNull(BenefitsService::getOptionCacheState());
    }

    public function testCreateInvalidatesCache(): void
    {
        $this->service->getEntities();
        $this->service->getHtmlOptions();
        $GLOBALS['__fa_next_id'] = 10;
        $this->service->create([
            'benefit_code' => 'DENTAL',
            'benefit_name' => 'Dental Plan',
        ]);
        $this->assertNull(BenefitsService::getOptionCacheState());
    }

    public function testDeactivateInvalidatesCache(): void
    {
        $this->service->getEntities();
        $this->service->getHtmlOptions();
        $this->service->deactivate(1);
        $this->assertNull(BenefitsService::getOptionCacheState());
    }

    // ─── Hook Response Methods ──────────────────────────────────────

    public function testHookGetBenefitsReturnsArrays(): void
    {
        $data = ['active_only' => true];
        $result = $this->service->hookGetBenefits($data);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('benefit_id', $result[0]);
        $this->assertArrayHasKey('benefit_name', $result[0]);
    }

    public function testHookGetBenefitDdlReturnsStrings(): void
    {
        $data = ['active_only' => true, 'blank_label' => '-- Pick --'];
        $result = $this->service->hookGetBenefitDDL($data);
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertStringContainsString('-- Pick --', $result[0]);
    }

    public function testHookGetBenefitHtmlOptionsReturnsOptionObjects(): void
    {
        $data = ['active_only' => true];
        $result = $this->service->hookGetBenefitHtmlOptions($data);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(HtmlOption::class, $result[0]);
    }

    // ─── Blank Option & Mandatory Validation (FR-006-006) ──────────

    public function testBlankOptionHasEmptyValue(): void
    {
        $rendered = $this->service->getDdl(true, '-- Select Benefit --');
        $this->assertStringContainsString('value=""', $rendered[0]);
        $this->assertStringContainsString('-- Select Benefit --', $rendered[0]);
    }

    public function testBlankOptionNotIncludedWhenNoBlankLabel(): void
    {
        $rendered = $this->service->getDdl(true, '');
        $this->assertStringNotContainsString('value=""', $rendered[0]);
    }
}
