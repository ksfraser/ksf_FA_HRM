<?php

declare(strict_types=1);

namespace Ksfraser\Tests\Unit\FAHRM;

use ksfraser\FrontAccounting\HRM\Entity\CommissionAssignment;
use PHPUnit\Framework\TestCase;

/**
 * CommissionAssignment entity hydration, accessors, and serialization.
 *
 * @BABOK Related: FR-HRM-006 - Employee-customer commission assignment
 */
class CommissionAssignmentTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $assignment = new CommissionAssignment(['id' => 1, 'person_id' => 5, 'customer_id' => 77]);
        $this->assertInstanceOf(CommissionAssignment::class, $assignment);
        $this->assertSame(1, $assignment->getId());
        $this->assertSame(5, $assignment->getPersonId());
        $this->assertSame(77, $assignment->getCustomerId());
        $this->assertSame('all', $assignment->getSource());
        $this->assertTrue($assignment->isActive());
    }

    public function testDefaultsAreApplied(): void
    {
        $assignment = new CommissionAssignment(['person_id' => 5, 'customer_id' => 77]);
        $this->assertSame(0, $assignment->getId());
        $this->assertSame('all', $assignment->getSource());
        $this->assertTrue($assignment->isActive());
    }

    public function testToArrayReturnsSnakeCaseKeys(): void
    {
        $assignment = new CommissionAssignment([
            'id' => 4,
            'person_id' => 7,
            'customer_id' => 88,
            'source' => 'square',
            'is_active' => 0,
        ]);
        $array = $assignment->toArray();
        $this->assertSame(4, $array['id']);
        $this->assertSame(7, $array['person_id']);
        $this->assertSame(88, $array['customer_id']);
        $this->assertSame('square', $array['source']);
        $this->assertSame(0, $array['is_active']);
    }
}
