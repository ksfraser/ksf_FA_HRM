# Test Plan - ksf_FA_HRM

## Overview

This document outlines the test strategy and approach for the ksf_FA_HRM module - Human Resources Management extension for FrontAccounting.

---

## 1. Test Scope

### 1.1 In Scope

- Employee CRUD operations
- Compensation management
- Benefits configuration
- Payroll GL integration
- CSV import functionality
- Module installation/uninstallation
- Permission-based access

### 1.2 Out of Scope

- FrontAccounting core functionality
- Third-party integrations (ksf_Documents, ksf_FA_Timesheets)
- Performance testing under heavy load

---

## 2. Test Types

### 2.1 Unit Testing

| Component | Test Focus |
|-----------|------------|
| InstallHook | Table creation, menu registration |
| PayrollGLentries | Journal entry generation, GL mapping |
| Import Processing | Field mapping, upsert logic |

### 2.2 Integration Testing

| Integration Point | Test Focus |
|------------------|------------|
| FA Hook System | Module install/uninstall triggers |
| FA Menu System | Menu item creation |
| FA Preferences | Default settings |
| FA GL | Journal entry posting |

### 2.3 Functional Testing

| Feature | Test Cases |
|---------|------------|
| Employee Management | Create, Read, Update, Delete employee |
| Benefits | Create benefit, assign to employee |
| Import | CSV upload, field mapping, data import |

---

## 3. Test Environment

### 3.1 Requirements

- FrontAccounting 2.4+ installed
- PHP 8.1+
- MySQL 5.7+
- Clean test database

### 3.2 Setup Steps

1. Install FrontAccounting
2. Create test company
3. Install ksf_FA_HRM module
4. Verify tables created
5. Configure test GL codes

---

## 4. Test Cases

### 4.1 Module Installation Tests

| Test ID | Description | Expected Result |
|---------|-------------|-----------------|
| INST-001 | Install module | Tables created, menu visible |
| INST-002 | Check ksf_hrm_employees table | Table exists with correct schema |
| INST-003 | Check ksf_hrm_grades table | Table exists with correct schema |
| INST-004 | Check ksf_hrm_benefits table | Table exists with correct schema |
| INST-005 | Check ksf_hrm_compensation table | Table exists with correct schema |
| INST-006 | Verify HRM menu item | Menu appears in navigation |
| INST-007 | Verify default preferences | Preferences set correctly |
| INST-008 | Uninstall module | All tables dropped |

### 4.2 Employee Management Tests

| Test ID | Description | Expected Result |
|---------|-------------|-----------------|
| EMP-001 | Create new employee | Record created in database |
| EMP-002 | Read employee by ID | Correct data returned |
| EMP-003 | Update employee | Changes saved to database |
| EMP-004 | Delete employee | Status changed to terminated |
| EMP-005 | Search employees by department | Correct filtered results |
| EMP-006 | Search employees by status | Correct filtered results |
| EMP-007 | Create employee with duplicate number | Error or update existing |

### 4.3 Compensation Tests

| Test ID | Description | Expected Result |
|---------|-------------|-----------------|
| COMP-001 | Create salary grade | Grade record created |
| COMP-002 | Set employee compensation | Compensation stored correctly |
| COMP-003 | Verify salary within grade range | Validation works |
| COMP-004 | Configure overtime | OT eligibility stored |
| COMP-005 | Set hourly rate | Rate stored correctly |

### 4.4 Benefits Tests

| Test ID | Description | Expected Result |
|---------|-------------|-----------------|
| BEN-001 | Create percentage benefit | Benefit created with rate |
| BENEFIT-002 | Create fixed amount benefit | Benefit created with amount |
| BENEFIT-003 | Configure mandatory benefit | is_mandatory flag set |
| BENEFIT-004 | Set GL codes for benefit | Expense/liability codes stored |
| BENEFIT-005 | Calculate employer contribution | Correct calculation |

### 4.5 Payroll GL Tests

| Test ID | Description | Expected Result |
|---------|-------------|-----------------|
| PAY-001 | Generate journal entry | Entry created with correct structure |
| PAY-002 | Test reference format | PR-YYYYMM-XXX format |
| PAY-003 | Test GL code mapping | Correct mapping returned |
| PAY-004 | Post to GL | Journal created in FA |
| PAY-005 | Verify balanced entry | Debits = Credits |

### 4.6 Import Tests

| Test ID | Description | Expected Result |
|---------|-------------|-----------------|
| IMP-001 | Upload valid CSV | File processed successfully |
| IMP-002 | Field auto-mapping | Common fields auto-mapped |
| IMP-003 | Manual field mapping | User can map fields |
| IMP-004 | Import new employee | New record created |
| IMP-005 | Import existing employee | Record updated |
| IMP-006 | Import missing required field | Row skipped, error logged |
| IMP-007 | Import empty file | Error message displayed |

---

## 5. Test Data

### 5.1 Employee Test Data

```php
$testEmployee = [
    'employee_number' => 'EMP001',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'john.doe@company.com',
    'phone' => '555-1234',
    'department' => 'Engineering',
    'job_title' => 'Software Developer',
    'status' => 'Active',
    'hire_date' => '2024-01-15',
];
```

### 5.2 Compensation Test Data

```php
$testCompensation = [
    'annual_salary' => 75000.00,
    'hourly_rate' => 36.06,
    'employee_type' => 'Salary',
    'ot_eligible' => true,
    'ot_multiplier' => 1.5,
    'gl_code_salary' => 'G01',
    'gl_code_overtime' => 'O01',
];
```

### 5.3 Benefit Test Data

```php
$testBenefit = [
    'code' => 'EI',
    'name' => 'Employment Insurance',
    'type' => 'Government',
    'employer_rate' => 2.28,
    'employee_rate' => 1.66,
    'is_mandatory' => true,
    'is_tax_deductible' => false,
    'gl_code_expense' => '2200',
    'gl_code_liability' => '2300',
];
```

### 5.4 Import CSV Sample

```csv
emp_no,first_name,last_name,email,phone,department,position,joined_date
EMP001,John,Doe,john.doe@company.com,555-1234,Engineering,Developer,2024-01-15
EMP002,Jane,Smith,jane.smith@company.com,555-5678,Marketing,Manager,2023-06-01
```

---

## 6. Test Execution

### 6.1 Installation Tests

1. Clean FA installation
2. Install ksf_FA_HRM
3. Verify all tables created via SQL
4. Check menu items in database
5. Check preferences in company_defaults

### 6.2 CRUD Tests

1. Use FA UI or direct database queries
2. Verify each operation completes
3. Verify data integrity

### 6.3 Integration Tests

1. Create test journal entry
2. Post to GL
3. Verify journal in FA

---

## 7. Defect Tracking

### 7.1 Severity Levels

| Severity | Description |
|----------|-------------|
| Critical | Data loss, system crash |
| High | Major functionality broken |
| Medium | Feature partially working |
| Low | Minor issue, cosmetic |

### 7.2 Example Defect Format

```
ID: DEF-001
Title: Employee import fails on null emp_no
Severity: High
Steps to Reproduce:
  1. Upload CSV with missing emp_no
  2. Process import
Expected: Skip row, continue processing
Actual: System error
```

---

## 8. Test Deliverables

| Deliverable | Description |
|-------------|-------------|
| This Test Plan | Overall strategy |
| Test Cases | Detailed test scenarios |
| Test Results | Execution logs |
| Defect Report | Issues found during testing |

---

## 9. Test Schedule

| Phase | Duration | Focus |
|-------|----------|-------|
| Unit Tests | 1 day | Component testing |
| Integration Tests | 2 days | FA integration |
| Functional Tests | 2 days | Feature validation |
| UAT Support | 3 days | User testing |

---

## 10. Regression Testing

After any code changes:
1. Re-run installation tests
2. Re-run import tests
3. Verify fix without breaking existing features
