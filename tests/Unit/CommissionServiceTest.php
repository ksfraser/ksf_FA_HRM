<?php

declare(strict_types=1);

namespace Ksfraser\Tests\Unit\FAHRM;

use ksfraser\FrontAccounting\HRM\Service\CommissionService;
use PHPUnit\Framework\TestCase;

/**
 * CommissionService calculation and order_imported handling.
 *
 * @BABOK Related: FR-HRM-007 - Commission calculation on order import
 */
class CommissionServiceTest extends TestCase
{
    /** @var CommissionService */
    private $service;

    protected function setUp(): void
    {
        $GLOBALS['__fa_select_queue'] = [];
        $GLOBALS['__fa_select_result'] = [];
        $GLOBALS['__fa_last_sql'] = '';
        $GLOBALS['__fa_next_id'] = 1;
        $this->service = new CommissionService();
    }

    public function testComputeCommissionPercent(): void
    {
        $amount = $this->service->computeCommission(1000.00, ['rate_type' => 'percent', 'rate' => 5]);
        $this->assertSame(50.0, $amount);
    }

    public function testComputeCommissionPercentWithDecimals(): void
    {
        $amount = $this->service->computeCommission(123.45, ['rate_type' => 'percent', 'rate' => 2.5]);
        $this->assertSame(3.09, $amount);
    }

    public function testComputeCommissionFixed(): void
    {
        $amount = $this->service->computeCommission(1000.00, ['rate_type' => 'fixed', 'rate' => 25]);
        $this->assertSame(25.0, $amount);
    }

    public function testComputeCommissionZeroRate(): void
    {
        $amount = $this->service->computeCommission(1000.00, ['rate_type' => 'percent', 'rate' => 0]);
        $this->assertSame(0.0, $amount);
    }

    public function testComputeCommissionDefaultsToPercent(): void
    {
        $amount = $this->service->computeCommission(200.00, ['rate' => 10]);
        $this->assertSame(20.0, $amount);
    }

    public function testOnOrderImportedCreatesEntriesForAssignedEmployees(): void
    {
        $GLOBALS['__fa_select_queue'] = [
            [],
            [['id' => 1, 'person_id' => 5, 'customer_id' => 77, 'source' => 'all', 'is_active' => 1]],
            [['rate_id' => 1, 'person_id' => 5, 'rate_type' => 'percent', 'rate' => '5.0000', 'is_active' => 1]],
        ];
        $GLOBALS['__fa_next_id'] = 1;

        $payload = [
            'source' => 'square',
            'source_order_id' => 'PAY_1',
            'fa_order_no' => 42,
            'fa_trans_type' => 10,
            'customer_id' => 77,
            'order_total' => 1000.00,
            'order_date' => '2026-08-10',
            'currency' => 'USD',
        ];

        $entryIds = $this->service->onOrderImported($payload);

        $this->assertSame([1], $entryIds);
        $this->assertStringContainsString('0_hrm_commission_entries', (string)$GLOBALS['__fa_last_sql']);
        $this->assertStringContainsString('50', (string)$GLOBALS['__fa_last_sql']);
    }

    public function testOnOrderImportedReturnsEmptyWithoutCustomer(): void
    {
        $entryIds = $this->service->onOrderImported(['fa_order_no' => 42]);

        $this->assertSame([], $entryIds);
    }

    public function testOnOrderImportedReturnsEmptyWithoutAssignments(): void
    {
        $GLOBALS['__fa_select_queue'] = [
            [],
            [],
        ];

        $entryIds = $this->service->onOrderImported([
            'fa_order_no' => 42,
            'fa_trans_type' => 10,
            'customer_id' => 77,
            'order_total' => 1000.00,
        ]);

        $this->assertSame([], $entryIds);
    }

    public function testOnOrderImportedReturnsEmptyWithoutActiveRate(): void
    {
        $GLOBALS['__fa_select_queue'] = [
            [],
            [['id' => 1, 'person_id' => 5, 'customer_id' => 77, 'source' => 'all', 'is_active' => 1]],
            [],
        ];

        $entryIds = $this->service->onOrderImported([
            'fa_order_no' => 42,
            'fa_trans_type' => 10,
            'customer_id' => 77,
            'order_total' => 1000.00,
        ]);

        $this->assertSame([], $entryIds);
    }

    public function testOnOrderImportedSkipsEmployeesWithExistingEntry(): void
    {
        $GLOBALS['__fa_select_queue'] = [
            [['entry_id' => 1, 'person_id' => 5, 'fa_order_no' => 42, 'fa_trans_type' => 10]],
            [['id' => 1, 'person_id' => 5, 'customer_id' => 77, 'source' => 'all', 'is_active' => 1]],
            [['rate_id' => 1, 'person_id' => 5, 'rate_type' => 'percent', 'rate' => '5.0000', 'is_active' => 1]],
        ];

        $entryIds = $this->service->onOrderImported([
            'fa_order_no' => 42,
            'fa_trans_type' => 10,
            'customer_id' => 77,
            'order_total' => 1000.00,
        ]);

        $this->assertSame([], $entryIds);
    }

    public function testOnOrderImportedCreatesEntryForEachAssignedEmployee(): void
    {
        $GLOBALS['__fa_select_queue'] = [
            [],
            [
                ['id' => 1, 'person_id' => 5, 'customer_id' => 77, 'source' => 'all', 'is_active' => 1],
                ['id' => 2, 'person_id' => 6, 'customer_id' => 77, 'source' => 'all', 'is_active' => 1],
            ],
            [['rate_id' => 1, 'person_id' => 5, 'rate_type' => 'percent', 'rate' => '5.0000', 'is_active' => 1]],
            [['rate_id' => 2, 'person_id' => 6, 'rate_type' => 'fixed', 'rate' => '10.0000', 'is_active' => 1]],
        ];
        $GLOBALS['__fa_next_id'] = 10;

        $entryIds = $this->service->onOrderImported([
            'fa_order_no' => 42,
            'fa_trans_type' => 10,
            'customer_id' => 77,
            'order_total' => 1000.00,
        ]);

        $this->assertSame([10, 11], $entryIds);
    }
}
