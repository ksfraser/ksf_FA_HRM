<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ksfraser\FrontAccounting\HRM\Service\TeamService;
use Ksfraser\HTML\Elements\HtmlOption;

/**
 * Unit tests for TeamService.
 *
 * Covers three-layer cache: entity → option (HtmlOption[]) → HTML (strings).
 *
 * @BABOK Related: BR-006
 * @BABOK Related: FR-006-001
 * @BABOK Related: FR-006-005
 */
class TeamServiceTest extends TestCase
{
    private TeamService $service;

    protected function setUp(): void
    {
        TeamService::invalidateCache();

        $GLOBALS['__fa_select_queue'] = [
            [
                [
                    'team_id' => 1,
                    'department_id' => 1,
                    'parent_team_id' => null,
                    'team_code' => 'IT-DEV',
                    'team_name' => 'Dev Team',
                    'description' => null,
                    'is_active' => 1,
                    'created_at' => '2024-01-01 00:00:00',
                    'updated_at' => '2024-01-01 00:00:00',
                ],
                [
                    'team_id' => 2,
                    'department_id' => 1,
                    'parent_team_id' => null,
                    'team_code' => 'IT-OPS',
                    'team_name' => 'Ops Team',
                    'description' => null,
                    'is_active' => 1,
                    'created_at' => '2024-01-01 00:00:00',
                    'updated_at' => '2024-01-01 00:00:00',
                ],
            ],
        ];

        $this->service = new TeamService();
    }

    protected function tearDown(): void
    {
        TeamService::invalidateCache();
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

        $cacheState = TeamService::getOptionCacheState();
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

    public function testGetHtmlOptionsFormatStringCustom(): void
    {
        $options = $this->service->getHtmlOptions(true, '', '{name}');
        $this->assertCount(2, $options);
        $this->assertSame('Dev Team', $options[0]->getLabel());
        $this->assertSame('Ops Team', $options[1]->getLabel());
    }

    // ─── Pre-rendered HTML DDL ──────────────────────────────────────

    public function testGetDdlReturnsStrings(): void
    {
        $rendered = $this->service->getDdl();
        $this->assertCount(2, $rendered);
        foreach ($rendered as $html) {
            $this->assertIsString($html);
            $this->assertStringContainsString('<option', $html);
            $this->assertStringContainsString('</option>', $html);
        }
    }

    public function testGetDdlWithBlankLabel(): void
    {
        $rendered = $this->service->getDdl(true, '-- Choose --');
        $this->assertCount(3, $rendered);
        $this->assertStringContainsString('-- Choose --', $rendered[0]);
        $this->assertStringContainsString('value=""', $rendered[0]);
    }

    public function testGetDdlWithSelectedId(): void
    {
        $rendered = $this->service->getDdl(true, '', '{code} - {name}', 1);
        $this->assertStringContainsString('selected', $rendered[0]);
        $this->assertStringNotContainsString('selected', $rendered[1]);
    }

    public function testGetDdlCacheDifferentiatesBySelectedId(): void
    {
        $r1 = $this->service->getDdl(true, '', '{code}', 0);
        $r2 = $this->service->getDdl(true, '', '{code}', 1);
        $r3 = $this->service->getDdl(true, '', '{code}', 2);

        $this->assertNotSame($r1, $r2);
        $this->assertNotSame($r2, $r3);
        $this->assertSame($r2, $this->service->getDdl(true, '', '{code}', 1));
    }

    // ─── Serialized Cache ───────────────────────────────────────────

    public function testGetSerializedCacheReturnsString(): void
    {
        $this->service->getHtmlOptions();
        $serialized = $this->service->getSerializedCache();
        $this->assertIsString($serialized);
        $this->assertNotEmpty($serialized);

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

    public function testRenderFromSerializedCacheInvalidStringReturnsEmpty(): void
    {
        $rendered = $this->service->renderFromSerializedCache('garbage');
        $this->assertSame([], $rendered);
    }

    // ─── Cache Invalidation ─────────────────────────────────────────

    public function testInvalidateCacheClearsAllLayers(): void
    {
        $this->service->getEntities();
        $this->service->getHtmlOptions();
        $this->assertNotNull(TeamService::getOptionCacheState());

        TeamService::invalidateCache();
        $this->assertNull(TeamService::getOptionCacheState());
    }

    public function testCreateInvalidatesCache(): void
    {
        $this->service->getEntities();
        $this->service->getHtmlOptions();
        $this->assertNotNull(TeamService::getOptionCacheState());

        $GLOBALS['__fa_next_id'] = 10;
        $this->service->create([
            'team_code' => 'NEW',
            'team_name' => 'New Team',
            'department_id' => 1,
        ]);
        $this->assertNull(TeamService::getOptionCacheState());
    }

    public function testUpdateInvalidatesCache(): void
    {
        $this->service->getEntities();
        $this->service->getHtmlOptions();
        $this->assertNotNull(TeamService::getOptionCacheState());

        $this->service->update(1, ['team_name' => 'Updated']);
        $this->assertNull(TeamService::getOptionCacheState());
    }

    public function testDeleteInvalidatesCache(): void
    {
        $this->service->getEntities();
        $this->service->getHtmlOptions();
        $this->assertNotNull(TeamService::getOptionCacheState());

        $this->service->delete(1);
        $this->assertNull(TeamService::getOptionCacheState());
    }

    // ─── Hook Response Methods ──────────────────────────────────────

    public function testHookGetTeamsReturnsArrays(): void
    {
        $data = ['active_only' => true];
        $result = $this->service->hookGetTeams($data);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('team_id', $result[0]);
        $this->assertArrayHasKey('team_name', $result[0]);
    }

    public function testHookGetTeamDdlReturnsStrings(): void
    {
        $data = ['active_only' => true, 'blank_label' => '-- Pick --'];
        $result = $this->service->hookGetTeamDDL($data);
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertStringContainsString('-- Pick --', $result[0]);
    }

    public function testHookGetTeamDdlWithSelectedId(): void
    {
        $data = ['active_only' => true, 'selected_id' => 2];
        $result = $this->service->hookGetTeamDDL($data);
        $this->assertStringContainsString('selected', $result[1]);
    }

    public function testHookGetTeamHtmlOptionsReturnsOptionObjects(): void
    {
        $data = ['active_only' => true];
        $result = $this->service->hookGetTeamHtmlOptions($data);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(HtmlOption::class, $result[0]);
    }

    // ─── Blank Option & Mandatory Validation (FR-006-006) ──────────

    public function testBlankOptionHasEmptyValue(): void
    {
        $rendered = $this->service->getDdl(true, '-- Select Team --');
        $this->assertStringContainsString('value=""', $rendered[0]);
        $this->assertStringContainsString('-- Select Team --', $rendered[0]);
    }

    public function testBlankOptionNotIncludedWhenNoBlankLabel(): void
    {
        $rendered = $this->service->getDdl(true, '');
        $this->assertStringNotContainsString('value=""', $rendered[0]);
    }
}
