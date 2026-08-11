<?php

declare(strict_types=1);

namespace Ksfraser\Tests\Unit\FAHRM;

use PHPUnit\Framework\TestCase;

class ModuleStructureTest extends TestCase
{
    public function testPayrollRepositoryExists(): void
    {
        $this->assertTrue(
            class_exists('ksfraser\FrontAccounting\HRM\Repository\PayrollRepository')
        );
    }

    public function testBenefitRepositoryExists(): void
    {
        $this->assertTrue(
            class_exists('ksfraser\FrontAccounting\HRM\Repository\BenefitRepository')
        );
    }

    public function testImportExists(): void
    {
        $this->assertTrue(
            file_exists(__DIR__ . '/../import.php') ||
            file_exists(__DIR__ . '/../../import.php')
        );
    }
}
