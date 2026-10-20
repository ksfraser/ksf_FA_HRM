<?php

declare(strict_types=1);

namespace Ksfraser\Tests\Unit\FA\HRM;

use PHPUnit\Framework\TestCase;

/**
 * The employee import upserted a flat row into `0_ksf_employee` (and
 * `0_ksf_employees` in the standalone variant). Neither table is created by
 * any schema in the estate, so every imported row went nowhere while the
 * import reported success.
 *
 * An employee in this estate is an FA person plus a row in HRM's own
 * employment table, so the import now uses FA's native person functions and
 * HRM's own table - never raw SQL against an FA core table.
 */
class EmployeeImportTest extends TestCase
{
    private string $moduleDir;

    protected function setUp(): void
    {
        $this->moduleDir = dirname(__DIR__, 2);
    }

    /**
     * @dataProvider importFileProvider
     */
    public function testImportDoesNotAddressTheNonExistentEmployeeTables(string $file): void
    {
        $contents = (string) file_get_contents($this->moduleDir . '/' . $file);

        $this->assertStringNotContainsString(
            'ksf_employee',
            $contents,
            $file . ' must not address 0_ksf_employee(s): no schema creates those tables'
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function importFileProvider(): array
    {
        return [
            'module import'      => ['import.php'],
            'standalone import'  => ['includes/import.php'],
        ];
    }

    public function testImportUsesTheSharedFaNativeEmployeeImport(): void
    {
        foreach (['import.php', 'includes/import.php'] as $file) {
            $contents = (string) file_get_contents($this->moduleDir . '/' . $file);

            $this->assertStringContainsString(
                'hrm_import_employee_row',
                $contents,
                $file . ' must delegate to the shared FA-native employee import'
            );
        }
    }

    public function testEmployeeImportCreatesThePersonWithFanativeFunctions(): void
    {
        $contents = (string) file_get_contents($this->moduleDir . '/includes/employee_import.inc');

        $this->assertStringContainsString('add_crm_person(', $contents);
        $this->assertStringContainsString('update_crm_person(', $contents);
        $this->assertStringContainsString(
            'hrm_contacts_employment',
            $contents,
            "the employment row belongs to HRM's own table"
        );
    }

    public function testEmployeeImportNeverWritesSqlAgainstFanCoreTables(): void
    {
        $contents = (string) file_get_contents($this->moduleDir . '/includes/employee_import.inc');

        $this->assertStringNotContainsString(
            'TB_PREF . "crm_persons',
            $contents,
            "the person must be written through FA's native functions, not raw SQL"
        );
    }

    public function testEmployeeImportPreservesTheExistingContactCategories(): void
    {
        $contents = (string) file_get_contents($this->moduleDir . '/includes/employee_import.inc');

        $this->assertStringContainsString(
            'hrm_employee_import_category_ids',
            $contents,
            'update_crm_person() replaces the contact relations, so they must be read back'
        );
    }
}
