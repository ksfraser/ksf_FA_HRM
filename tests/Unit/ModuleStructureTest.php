<?php

declare(strict_types=1);

namespace Ksfraser\Tests\Unit\FAHRM;

use PHPUnit\Framework\TestCase;

class ModuleStructureTest extends TestCase
{
    private string $moduleDir;
    
    protected function setUp(): void
    {
        $this->moduleDir = dirname(__DIR__, 2);
    }
    
    public function testIncludesDirectoryExists(): void
    {
        $this->assertDirectoryExists($this->moduleDir . '/includes');
    }
    
    public function testPayrollDbIncExists(): void
    {
        $this->assertFileExists($this->moduleDir . '/includes/payroll_db.inc');
    }
    
    public function testLeaveDbIncExists(): void
    {
        $this->assertFileExists($this->moduleDir . '/includes/leave_db.inc');
    }
    
    public function testBenefitsDbIncExists(): void
    {
        $this->assertFileExists($this->moduleDir . '/includes/benefits_db.inc');
    }
    
    public function testImportPhpExists(): void
    {
        $this->assertFileExists($this->moduleDir . '/includes/import.php');
    }
    
    public function testProjectDcsExists(): void
    {
        $this->assertDirectoryExists($this->moduleDir . '/ProjectDcs');
    }
}
