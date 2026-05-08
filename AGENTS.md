# AGENTS.md - ksf_FA_HRM#

## Architecture Overview#

This repository follows a **Layered Architecture** with clear separation of concerns:

### Core Principles#
- **SOLID**: Single Responsibility, Open/Closed, Liskov Substitution, Interface Segregation, Dependency Inversion#
- **DRY**: Don't Repeat Yourself - extract reusable logic#
- **TDD**: Test-Driven Development - write tests first#
- **DI**: Dependency Injection - inject dependencies, don't hardcode#
- **SRP**: Single Responsibility Principle - each class has one reason to change#

## Repository Structure#

```
ksf_FA_HRM/
├── sql/                    # Database schemas (FA TB_PREF tables)
│   ├── fa_contacts_pii.sql
│   ├── fa_contacts_banking.sql
│   ├── fa_contacts_employment.sql
│   ├── fa_dependent_details.sql
│   ├── fa_departments.sql
│   ├── fa_positions.sql
│   ├── fa_grades.sql
│   ├── fa_pay_elements.sql
│   ├── fa_salary_structure.sql
│   ├── fa_separation_reasons.sql
│   ├── ksf_hrm_benefits.sql
│   ├── ksf_hrm_employee_benefits.sql
│   ├── ksf_hrm_payroll.sql
│   └── ksf_hrm_leave_balances.sql
├── includes/              # FA-specific DB classes
│   ├── benefits_db.inc
│   ├── employee_benefits_db.inc
│   ├── payroll_db.inc
│   └── leave_db.inc
├── src/                    # Business logic (namespace: Ksf\FA\HRM\)
│   ├── Hooks/
│   │   └── InstallHook.php
│   ├── Services/
│   └── Models/
├── pages/                 # UI pages (FA admin)
├── composer.json
└── ProjectDocs/           # Project documentation
    ├── Requirements.md
    ├── RTM.md            # Requirements Traceability Matrix
    ├── BABOK.md         # Business Analysis Body of Knowledge
    └── UML.md           # UML diagrams
```

## Coding Standards#

### PHP Compatibility#
- **Target**: PHP 7.3+ (with eye to PHP 8.x upgrades)#
- Use `declare(strict_types=1);` at top of all PHP files#
- Avoid PHP 8+ features until we drop PHP 7.3 support#

### Naming Conventions#
- **HRM tables**: `fa_*` (shared) and `ksf_hrm_*` (HRM-specific)#
- **Install Hook**: `src/Hooks/InstallHook.php` (PSR-4)#
- **FA DB files**: `{table_name}_db.inc`#

### Documentation (UML/BABOK)#
```php
/**
 * Calculate payroll for employee
 * 
 * @param int $person_id The CRM person ID (employee)
 * @param array $period Pay period [start, end]
 * @return array Payroll data with earnings/deductions
 * 
 * @UML Note: See ProjectDocs/UML.md - Payroll Processing sequence diagram
 * @BABOK Related: BR-005 Payroll Management
 */
function calculate_payroll($person_id, $period) { ... }
```

## Testing Strategy#

### TDD Red-Green-Refactor#
1. **RED**: Write failing test#
2. **GREEN**: Write minimal code to pass#
3. **REFACTOR**: Improve code while keeping tests green#

## Design Patterns Used#

### Table Gateway Pattern#
- Each `fa_*` and `ksf_hrm_*` table has corresponding `_db.inc` file#
- Functions: `write_`, `get_`, `delete_` for CRUD operations#

### Hook Pattern (FA Native)#
- Uses FA's `update_databases()` for multi-SQL file handling#
- `activate()` method processes SQL files in dependency order#

### Table Ownership#
- **HRM owns**: `fa_positions`, `fa_grades`, `fa_pay_elements`, `fa_salary_structure`, `fa_separation_reasons`#
- **HRM owns (employment-specific)**: `fa_contacts_employment`, `fa_contacts_pii`, `fa_contacts_banking`, `fa_dependent_details`, `fa_departments`#
- **HRM-specific**: `ksf_hrm_benefits`, `ksf_hrm_employee_benefits`, `ksf_hrm_payroll`, `ksf_hrm_leave_balances`#

## Version Tagging#

Follow Semantic Versioning (SemVer): `MAJOR.MINOR.PATCH`#

```bash
git tag -a v1.0.0 -m "Initial HRM module with employment management"
git push origin v1.0.0
```

## Composer/Packagist#

```json
{
    "name": "ksfraser/ksf_fa_hrm",
    "description": "HRM Module for FrontAccounting",
    "type": "frontaccounting-module",
    "require": {
        "php": ">=7.3",
        "ksfraser/ksf_fa_crm": "*",
        "ksfraser/ksf_fa_hrm_core": "*"
    },
    "autoload": {
        "psr-4": {
            "Ksf\\FA\\HRM\\": "src/"
        }
    }
}
```

## RTM (Requirements Traceability Matrix)#

See `ProjectDocs/RTM.md` for full traceability:#

| Req ID | Description | Test Case | Code File | Version |
|--------|-------------|-----------|----------|---------|
| REQ-001 | Employee as Contact | testEmployeeCreation | sql/fa_contacts_employment.sql | v1.0.0 |
| REQ-002 | PII Separation | testPiiStorage | sql/fa_contacts_pii.sql | v1.0.0 |
| REQ-003 | Salary Structure | testSalaryCalc | sql/fa_salary_structure.sql | v1.1.0 |
| REQ-004 | Payroll Processing | testPayrollCalc | includes/payroll_db.inc | v1.2.0 |

## BABOK Alignment#

See `ProjectDocs/BABOK.md` for business analysis alignment:#

### Business Requirements (BABOK)#
- **BR-004**: Employee Management - Employees as CRM contacts#
- **BR-005**: Payroll Management - Salary calculations with grade/position#
- **BR-006**: Benefits Administration - Employee benefit assignments#
- **BR-007**: Leave Management - Leave balances and requests#

## UML Documentation#

See `ProjectDocs/UML.md` for:#
- Class diagrams#
- Sequence diagrams#
- Component diagrams#

### Example: Employee Onboarding Sequence#
```
HR -> HRM: Create Employee
HRM -> CRM: create_contact(type='employee')
CRM -> DB: Insert 0_crm_persons
HRM -> DB: Insert fa_contacts_employment
HRM -> DB: Insert fa_contacts_pii
HRM -> User: Display success
```

## Dependencies#

- **ksf_FA_HRM_Core** (business logic - framework-agnostic)#
- **ksf_FA_CRM** (contact system)#
- **FrontAccounting 2.4+** (FA core)#
- **0_crm_persons** (FA built-in table)#
