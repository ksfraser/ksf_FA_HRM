<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ksfraser\FrontAccounting\HRM\Repository\LookupRepository;

/**
 * Unit tests for LookupRepository's leave-type catalogue methods.
 *
 * Leave types have no service/entity layer — the tab controller talks to this
 * repository directly, so these tests cover the update/deactivate path that
 * previously did not exist (only save/list did).
 *
 * @BABOK Related: FR-HRM-001
 */
class LookupRepositoryTest extends TestCase
{
    /** @var LookupRepository */
    private $repo;

    protected function setUp(): void
    {
        $GLOBALS['__fa_last_sql'] = '';
        unset($GLOBALS['__fa_select_queue'], $GLOBALS['__fa_current_result']);
        $this->repo = new LookupRepository();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__fa_select_queue'], $GLOBALS['__fa_current_result'], $GLOBALS['__fa_last_sql']);
    }

    public function testGetLeaveTypesReturnsAllRowsUnfiltered(): void
    {
        $GLOBALS['__fa_select_queue'] = [[
            ['leave_type_id' => 1, 'type_code' => 'ANN', 'type_name' => 'Annual', 'default_days' => 20, 'is_paid' => 1, 'is_active' => 1],
            ['leave_type_id' => 2, 'type_code' => 'OLD', 'type_name' => 'Retired', 'default_days' => 5, 'is_paid' => 0, 'is_active' => 0],
        ]];
        $rows = $this->repo->getLeaveTypes();
        $this->assertCount(2, $rows);
        $this->assertStringNotContainsString('is_active = 1', (string) $GLOBALS['__fa_last_sql']);
    }

    public function testUpdateLeaveTypeWritesOnlySuppliedFields(): void
    {
        $GLOBALS['__fa_last_sql'] = '';
        $this->repo->updateLeaveType(4, ['type_name' => 'Annual Leave']);
        $sql = (string) $GLOBALS['__fa_last_sql'];
        $this->assertStringContainsString('UPDATE', $sql);
        $this->assertStringContainsString('0_leave_types', $sql);
        $this->assertStringContainsString('type_name', $sql);
        $this->assertStringContainsString('leave_type_id = 4', $sql);
        $this->assertStringNotContainsString('type_code', $sql);
    }

    public function testUpdateLeaveTypeWithNoKnownFieldsIsNoop(): void
    {
        $GLOBALS['__fa_last_sql'] = '';
        $this->repo->updateLeaveType(4, ['not_a_column' => 'x']);
        $this->assertStringNotContainsString('UPDATE', (string) $GLOBALS['__fa_last_sql']);
    }

    public function testDeactivateLeaveTypeClearsFlagOnly(): void
    {
        $GLOBALS['__fa_last_sql'] = '';
        $this->repo->deactivateLeaveType(7);
        $sql = (string) $GLOBALS['__fa_last_sql'];
        $this->assertStringContainsString('UPDATE', $sql);
        $this->assertStringContainsString('0_leave_types', $sql);
        $this->assertStringContainsString('is_active = 0', $sql);
        $this->assertStringContainsString('leave_type_id = 7', $sql);
        $this->assertStringNotContainsString('DELETE', $sql);
    }

    public function testSaveLeaveTypeInsertsAndReturnsId(): void
    {
        $GLOBALS['__fa_last_sql'] = '';
        $GLOBALS['__fa_next_id'] = 42;
        $id = $this->repo->saveLeaveType([
            'type_code' => 'SICK',
            'type_name' => 'Sick Leave',
            'default_days' => 10,
            'is_paid' => 1,
            'is_active' => 1,
        ]);
        $this->assertSame(42, $id);
        $this->assertStringContainsString('INSERT INTO', (string) $GLOBALS['__fa_last_sql']);
        $this->assertStringContainsString('0_leave_types', (string) $GLOBALS['__fa_last_sql']);
    }
}
