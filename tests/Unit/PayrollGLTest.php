<?php

declare(strict_types=1);

namespace Ksfraser\Tests\Unit\FA\HRM;

use PHPUnit\Framework\TestCase;

class PayrollGLTest extends TestCase
{
    public function testGLEntriesClassExists(): void
    {
        $this->assertTrue(
            class_exists('Ksfraser\GL\PayrollGLentries') ||
            file_exists(__DIR__ . '/../../src/Ksfraser/GL/PayrollGLentries.php')
        );
    }

    public function testInstallHookExists(): void
    {
        $this->assertTrue(
            class_exists('Ksfraser\Hooks\InstallHook') ||
            file_exists(__DIR__ . '/../../src/Ksfraser/Hooks/InstallHook.php')
        );
    }
}
