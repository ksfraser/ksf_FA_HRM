<?php

declare(strict_types=1);

namespace Ksfraser\Tests\Unit\FAHRM;

use PHPUnit\Framework\TestCase;

/**
 * Capability contract and order_imported listener on hooks_ksf_FA_HRM.
 *
 * @BABOK Related: FR-HRM-008 - Inter-module capability contract
 */
class HooksTest extends TestCase
{
    /** @var \hooks_ksf_FA_HRM */
    private $hooks;

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/hooks.php';
        $this->hooks = new \hooks_ksf_FA_HRM();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__fa_select_queue'], $GLOBALS['__fa_select_result'], $GLOBALS['__fa_last_sql']);
    }

    public function testGetModuleConstants(): void
    {
        $data = [];
        $result = $this->hooks->getModuleConstants($data);

        $this->assertArrayHasKey('KSF_HRM_MODULE_NAME', $result);
        $this->assertSame('ksf_FA_HRM', $result['KSF_HRM_MODULE_NAME']);
        $this->assertArrayHasKey('KSF_HRM_CAPABILITIES', $result);
        $this->assertArrayHasKey('constants', $data);
    }

    public function testGetModuleCapabilities(): void
    {
        $data = [];
        $result = $this->hooks->getModuleCapabilities($data);

        $this->assertArrayHasKey('commission', $result);
        $this->assertArrayHasKey('description', $result['commission']);
        $this->assertArrayHasKey('methods', $result['commission']);
        $this->assertArrayHasKey('events', $result['commission']);
        $this->assertContains('ORDER_IMPORTED', $result['commission']['events']);
        $this->assertArrayHasKey('capabilities', $data);
    }

    public function testHasCapabilityCommission(): void
    {
        $data = [];
        $result = $this->hooks->hasCapability($data, ['capability' => 'commission']);

        $this->assertTrue($result);
        $this->assertTrue($data['has_capability']);
        $this->assertSame('commission', $data['capability_checked']);
    }

    public function testHasCapabilityEmployee(): void
    {
        $data = [];
        $result = $this->hooks->hasCapability($data, ['capability' => 'employee']);

        $this->assertTrue($result);
    }

    public function testHasCapabilityUnknown(): void
    {
        $data = [];
        $result = $this->hooks->hasCapability($data, ['capability' => 'nonexistent']);

        $this->assertFalse($result);
        $this->assertFalse($data['has_capability']);
    }

    public function testHasCapabilityNoCapabilityReturnsFalse(): void
    {
        $data = [];
        $result = $this->hooks->hasCapability($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('error', $data);
    }

    public function testRespondToCapabilityRequestCapabilities(): void
    {
        $data = [];
        $result = $this->hooks->respondToCapabilityRequest($data, ['request' => 'capabilities']);

        $this->assertArrayHasKey('commission', $result);
        $this->assertSame('capabilities', $data['request']);
        $this->assertSame('ksf_FA_HRM', $data['module']);
    }

    public function testRespondToCapabilityRequestConstants(): void
    {
        $data = [];
        $result = $this->hooks->respondToCapabilityRequest($data, ['request' => 'constants']);

        $this->assertArrayHasKey('KSF_HRM_MODULE_NAME', $result);
    }

    public function testRespondToCapabilityRequestHasCapability(): void
    {
        $data = [];
        $result = $this->hooks->respondToCapabilityRequest($data, ['request' => 'has:commission']);

        $this->assertTrue($result);
    }

    public function testRespondToCapabilityRequestUnknownReturnsNull(): void
    {
        $data = [];
        $result = $this->hooks->respondToCapabilityRequest($data, ['request' => 'unknown']);

        $this->assertNull($result);
        $this->assertArrayHasKey('error', $data);
    }

    public function testOrderImportedListenerCreatesCommissionEntries(): void
    {
        $GLOBALS['__fa_select_queue'] = [
            [],
            [['id' => 1, 'person_id' => 5, 'customer_id' => 77, 'source' => 'all', 'is_active' => 1]],
            [['rate_id' => 1, 'person_id' => 5, 'rate_type' => 'percent', 'rate' => '5.0000', 'is_active' => 1]],
        ];
        $GLOBALS['__fa_next_id'] = 3;

        $data = [
            'source' => 'square',
            'source_order_id' => 'PAY_1',
            'fa_order_no' => 42,
            'fa_trans_type' => 10,
            'customer_id' => 77,
            'order_total' => 1000.00,
            'order_date' => '2026-08-10',
            'currency' => 'USD',
        ];

        $this->hooks->order_imported($data);

        $this->assertArrayHasKey('commissions_created', $data);
        $this->assertSame(1, $data['commissions_created']);
    }

    public function testOrderImportedListenerWithoutCustomerCreatesNone(): void
    {
        $data = ['fa_order_no' => 42];

        $this->hooks->order_imported($data);

        $this->assertArrayHasKey('commissions_created', $data);
        $this->assertSame(0, $data['commissions_created']);
    }
}
