<?php

declare(strict_types=1);

namespace Ksfraser\Tests\Unit\FAHRM;

use ksfraser\FrontAccounting\HRM\Entity\CommissionRate;
use PHPUnit\Framework\TestCase;

/**
 * CommissionRate entity hydration, accessors, and serialization.
 *
 * @BABOK Related: FR-HRM-004 - Commission rate configuration
 */
class CommissionRateTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $rate = new CommissionRate(['rate_id' => 1, 'person_id' => 5, 'rate' => '5.0000']);
        $this->assertInstanceOf(CommissionRate::class, $rate);
        $this->assertSame(1, $rate->getRateId());
        $this->assertSame(5, $rate->getPersonId());
        $this->assertSame(5.0, $rate->getRate());
        $this->assertSame('percent', $rate->getRateType());
        $this->assertSame('all', $rate->getSource());
        $this->assertTrue($rate->isActive());
    }

    public function testDefaultsAreApplied(): void
    {
        $rate = new CommissionRate(['person_id' => 5, 'rate' => '5.0000']);
        $this->assertSame(0, $rate->getRateId());
        $this->assertSame('percent', $rate->getRateType());
        $this->assertSame('all', $rate->getSource());
        $this->assertTrue($rate->isActive());
        $this->assertSame('', $rate->getEffectiveFrom());
        $this->assertNull($rate->getEffectiveTo());
    }

    public function testToArrayReturnsSnakeCaseKeys(): void
    {
        $rate = new CommissionRate(['rate_id' => 3, 'person_id' => 7, 'rate_type' => 'fixed', 'rate' => '25.0000']);
        $array = $rate->toArray();
        $this->assertSame(3, $array['rate_id']);
        $this->assertSame(7, $array['person_id']);
        $this->assertSame('fixed', $array['rate_type']);
        $this->assertSame(25.0, $array['rate']);
    }
}
