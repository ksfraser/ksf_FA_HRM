<?php

declare(strict_types=1);

namespace Ksfraser\Tests\Unit\FAHRM;

use ksfraser\FrontAccounting\HRM\Entity\CommissionRate;
use ksfraser\FrontAccounting\HRM\Entity\CommissionAssignment;
use ksfraser\FrontAccounting\HRM\Repository\CommissionRepository;
use PHPUnit\Framework\TestCase;

/**
 * CommissionRepository data access against a GLOBALS-backed fake DB.
 *
 * @BABOK Related: FR-HRM-004/005 - Commission rates and entries persistence
 */
class CommissionRepositoryTest extends TestCase
{
    /** @var CommissionRepository */
    private $repo;

    protected function setUp(): void
    {
        $GLOBALS['__fa_select_queue'] = [];
        $GLOBALS['__fa_select_result'] = [];
        $GLOBALS['__fa_last_sql'] = '';
        $GLOBALS['__fa_next_id'] = 1;
        $this->repo = new CommissionRepository();
    }

    public function testSaveRateInsertsIntoCommissionRates(): void
    {
        $GLOBALS['__fa_next_id'] = 5;

        $id = $this->repo->saveRate([
            'person_id' => 3,
            'source' => 'square',
            'rate_type' => 'percent',
            'rate' => 5,
            'effective_from' => '2026-01-01',
        ]);

        $this->assertSame(5, $id);
        $this->assertStringContainsString('INSERT INTO', (string)$GLOBALS['__fa_last_sql']);
        $this->assertStringContainsString('0_hrm_commission_rates', (string)$GLOBALS['__fa_last_sql']);
    }

    public function testUpdateRateBuildsUpdateStatement(): void
    {
        $this->repo->updateRate(2, ['rate' => 7.5, 'is_active' => 0]);

        $this->assertStringContainsString('UPDATE', (string)$GLOBALS['__fa_last_sql']);
        $this->assertStringContainsString('0_hrm_commission_rates', (string)$GLOBALS['__fa_last_sql']);
        $this->assertStringContainsString('WHERE rate_id = 2', (string)$GLOBALS['__fa_last_sql']);
    }

    public function testDeleteRateBuildsDeleteStatement(): void
    {
        $this->repo->deleteRate(9);

        $this->assertStringContainsString('DELETE FROM', (string)$GLOBALS['__fa_last_sql']);
        $this->assertStringContainsString('0_hrm_commission_rates', (string)$GLOBALS['__fa_last_sql']);
        $this->assertStringContainsString('rate_id = 9', (string)$GLOBALS['__fa_last_sql']);
    }

    public function testFindActiveRateByPersonReturnsRate(): void
    {
        $GLOBALS['__fa_select_result'] = [[
            'rate_id' => 1,
            'person_id' => 3,
            'rate_type' => 'percent',
            'rate' => '5.0000',
            'is_active' => 1,
        ]];

        $rate = $this->repo->findActiveRateByPerson(3, '2026-08-10', 'square');

        $this->assertInstanceOf(CommissionRate::class, $rate);
        $this->assertSame(3, $rate->getPersonId());
        $this->assertSame(5.0, $rate->getRate());
        $this->assertStringContainsString('0_hrm_commission_rates', (string)$GLOBALS['__fa_last_sql']);
        $this->assertStringContainsString('is_active = 1', (string)$GLOBALS['__fa_last_sql']);
        $this->assertStringContainsString('LIMIT 1', (string)$GLOBALS['__fa_last_sql']);
    }

    public function testFindActiveRateByPersonReturnsNullWhenNoRows(): void
    {
        $GLOBALS['__fa_select_result'] = [];

        $rate = $this->repo->findActiveRateByPerson(3, '2026-08-10', 'square');

        $this->assertNull($rate);
    }

    public function testFindAssignmentsByCustomerReturnsAssignments(): void
    {
        $GLOBALS['__fa_select_result'] = [
            ['id' => 1, 'person_id' => 3, 'customer_id' => 77, 'source' => 'all', 'is_active' => 1],
        ];

        $assignments = $this->repo->findAssignmentsByCustomer(77, 'square');

        $this->assertCount(1, $assignments);
        $this->assertInstanceOf(CommissionAssignment::class, $assignments[0]);
        $this->assertSame(77, $assignments[0]->getCustomerId());
        $this->assertStringContainsString('customer_id = 77', (string)$GLOBALS['__fa_last_sql']);
    }

    public function testSaveAssignmentInserts(): void
    {
        $GLOBALS['__fa_next_id'] = 8;

        $id = $this->repo->saveAssignment(['person_id' => 3, 'customer_id' => 77]);

        $this->assertSame(8, $id);
        $this->assertStringContainsString('0_hrm_commission_assignments', (string)$GLOBALS['__fa_last_sql']);
    }

    public function testDeleteAssignmentBuildsDeleteStatement(): void
    {
        $this->repo->deleteAssignment(4);

        $this->assertStringContainsString('0_hrm_commission_assignments', (string)$GLOBALS['__fa_last_sql']);
        $this->assertStringContainsString('id = 4', (string)$GLOBALS['__fa_last_sql']);
    }

    public function testCreateEntryInsertsAndReturnsId(): void
    {
        $GLOBALS['__fa_next_id'] = 21;

        $id = $this->repo->createEntry([
            'person_id' => 3,
            'fa_order_no' => 42,
            'fa_trans_type' => 10,
            'source' => 'square',
            'source_order_id' => 'PAY_1',
            'customer_id' => 77,
            'order_total' => 1000,
            'commission_amount' => 50,
            'rate' => 5,
            'status' => 'pending',
            'order_date' => '2026-08-10',
        ]);

        $this->assertSame(21, $id);
        $this->assertStringContainsString('0_hrm_commission_entries', (string)$GLOBALS['__fa_last_sql']);
        $this->assertStringContainsString('commission_amount', (string)$GLOBALS['__fa_last_sql']);
    }

    public function testFindEntriesByOrderFiltersByOrder(): void
    {
        $GLOBALS['__fa_select_result'] = [[
            'entry_id' => 1,
            'person_id' => 3,
            'fa_order_no' => 42,
            'fa_trans_type' => 10,
        ]];

        $entries = $this->repo->findEntriesByOrder(42, 10);

        $this->assertCount(1, $entries);
        $this->assertStringContainsString('fa_order_no = 42', (string)$GLOBALS['__fa_last_sql']);
        $this->assertStringContainsString('fa_trans_type = 10', (string)$GLOBALS['__fa_last_sql']);
    }

    public function testUpdateEntryStatusBuildsUpdateStatement(): void
    {
        $this->repo->updateEntryStatus(7, 'paid');

        $this->assertStringContainsString('UPDATE', (string)$GLOBALS['__fa_last_sql']);
        $this->assertStringContainsString('0_hrm_commission_entries', (string)$GLOBALS['__fa_last_sql']);
        $this->assertStringContainsString('paid', (string)$GLOBALS['__fa_last_sql']);
        $this->assertStringContainsString('entry_id = 7', (string)$GLOBALS['__fa_last_sql']);
    }

    public function testListRatesReturnsArrayOfRates(): void
    {
        $GLOBALS['__fa_select_result'] = [
            ['rate_id' => 1, 'person_id' => 3, 'rate_type' => 'percent', 'rate' => '5.0000'],
        ];

        $rates = $this->repo->listRates();

        $this->assertCount(1, $rates);
        $this->assertInstanceOf(CommissionRate::class, $rates[0]);
        $this->assertStringContainsString('0_hrm_commission_rates', (string)$GLOBALS['__fa_last_sql']);
    }
}
