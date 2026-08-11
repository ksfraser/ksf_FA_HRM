<?php

declare(strict_types=1);

namespace Ksfraser\Tests\Unit\FAHRM;

use ksfraser\FrontAccounting\HRM\Entity\CommissionEntry;
use PHPUnit\Framework\TestCase;

/**
 * CommissionEntry entity hydration, accessors, and serialization.
 *
 * @BABOK Related: FR-HRM-005 - Commission entry generation
 */
class CommissionEntryTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $entry = new CommissionEntry([
            'entry_id' => 11,
            'person_id' => 5,
            'fa_order_no' => 42,
            'fa_trans_type' => 10,
            'commission_amount' => '50.00',
        ]);
        $this->assertInstanceOf(CommissionEntry::class, $entry);
        $this->assertSame(11, $entry->getEntryId());
        $this->assertSame(5, $entry->getPersonId());
        $this->assertSame(42, $entry->getFaOrderNo());
        $this->assertSame(10, $entry->getFaTransType());
        $this->assertSame(50.0, $entry->getCommissionAmount());
        $this->assertSame('pending', $entry->getStatus());
        $this->assertSame('all', $entry->getSource());
    }

    public function testDefaultsAreApplied(): void
    {
        $entry = new CommissionEntry(['person_id' => 5, 'fa_order_no' => 42]);
        $this->assertSame(0, $entry->getEntryId());
        $this->assertSame(0.0, $entry->getCommissionAmount());
        $this->assertSame('pending', $entry->getStatus());
        $this->assertSame('', $entry->getOrderDate());
        $this->assertNull($entry->getSourceOrderId());
    }

    public function testToArrayReturnsSnakeCaseKeys(): void
    {
        $entry = new CommissionEntry([
            'entry_id' => 2,
            'person_id' => 7,
            'fa_order_no' => 99,
            'fa_trans_type' => 10,
            'commission_amount' => '12.34',
            'status' => 'approved',
        ]);
        $array = $entry->toArray();
        $this->assertSame(2, $array['entry_id']);
        $this->assertSame(99, $array['fa_order_no']);
        $this->assertSame(12.34, $array['commission_amount']);
        $this->assertSame('approved', $array['status']);
    }
}
