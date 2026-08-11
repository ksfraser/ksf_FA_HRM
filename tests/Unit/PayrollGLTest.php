<?php

declare(strict_types=1);

namespace Ksfraser\Tests\Unit\FA\HRM;

use PHPUnit\Framework\TestCase;

class PayrollGLTest extends TestCase
{
    public function testGLEntriesClassExists(): void
    {
        $this->assertTrue(
            class_exists('Ksfraser\FrontAccounting\HRM\GL\PayrollGLentries') ||
            file_exists(__DIR__ . '/../../src/GL/PayrollGLentries.php')
        );
    }

    public function testInstallHookExists(): void
    {
        $this->assertTrue(
            class_exists('hooks_ksf_FA_HRM', false) ||
            file_exists(__DIR__ . '/../../hooks.php')
        );
    }
}
