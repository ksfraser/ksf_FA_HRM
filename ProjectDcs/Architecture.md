# Architecture - ksf_FA_HRM

## Overview

This document describes the technical architecture of the ksf_FA_HRM module - a Human Resources Management extension for FrontAccounting.

---

## 1. System Architecture

### 1.1 Module Position

The ksf_FA_HRM module sits as an extension layer on top of FrontAccounting:

```
┌─────────────────────────────────────────┐
│           FrontAccounting Core          │
│   (GL, AP, AR, Banking, Reporting)      │
├─────────────────────────────────────────┤
│           ksf_FA_HRM Module             │
│   (Employee, Compensation, Benefits)   │
├─────────────────────────────────────────┤
│       ksfraser/ksf-hrm Library         │
│        (Core HRM Functionality)        │
├─────────────────────────────────────────┤
│            MySQL Database               │
└─────────────────────────────────────────┘
```

### 1.2 Technology Stack

| Component | Technology |
|-----------|------------|
| Platform | FrontAccounting 2.4+ |
| Language | PHP 8.1+ |
| Database | MySQL 5.7+ |
| External Library | ksfraser/ksf-hrm |
| Integration | DataIO Framework |

---

## 2. Module Structure

### 2.1 Directory Layout

```
ksf_FA_HRM/
├── includes/
│   └── import.php              # CSV import integration
├── src/
│   ├── Hooks/
│   │   └── InstallHook.php    # Installation lifecycle
│   └── GL/
│       └── PayrollGLentries.php # Payroll GL posting
├── sql/
│   └── install.sql            # SQL installation scripts
├── ProjectDcs/                # Project documentation
├── install.php               # Module entry point
├── import.php                # Import configuration
├── composer.json             # Composer dependencies
└── README.md                 # Module documentation
```

### 2.2 Component Responsibilities

#### install.php
- Module registration with FrontAccounting
- Defines access levels and permissions
- Registers install/uninstall hooks
- Adds extension options widget

#### includes/import.php
- Implements CSV import menu
- Provides field mapping UI
- Processes employee import with upsert logic
- Integrates with ksf_DataIO framework

#### src/Hooks/InstallHook.php
- Database table creation/management
- Menu item registration
- Default preference configuration
- Clean uninstallation

#### src/GL/PayrollGLentries.php
- Journal entry generation
- GL posting integration
- GL code mapping management

---

## 3. Database Architecture

### 3.1 Entity Relationship Diagram

```
┌──────────────────┐       ┌────────────────────┐
│ ksf_hrm_employees│       │  ksf_hrm_grades    │
├──────────────────┤       ├────────────────────┤
│ id (PK)          │       │ id (PK)            │
│ employee_number  │       │ code (UNIQUE)      │
│ first_name       │       │ name               │
│ last_name        │       │ min_salary         │
│ email            │       │ max_salary         │
│ phone            │──────<│ min_hourly         │
│ department       │       │ max_hourly         │
│ job_title        │       │ level              │
│ status           │       └────────┬───────────┘
│ hire_date        │                │
│ manager_id (FK)  │                │
│ team_id (FK)     │       ┌────────▼───────────┐
└────────┬─────────┘       │ksf_hrm_compensation│
         │                ├──────────────────────┤
         │                │ id (PK)              │
┌────────▼─────────┐       │ employee_id (FK)    │──┐
│ksf_hrm_compensation│     │ grade_id (FK)       │<─┘
├──────────────────┤       │ annual_salary       │
│ id (PK)          │       │ hourly_rate         │
│ employee_id (FK) │       │ employee_type       │
│ grade_id (FK)    │       │ ot_eligible         │
│ annual_salary    │       │ gl_code_salary      │
│ hourly_rate      │       │ gl_code_overtime    │
└──────────────────┘       └──────────────────────┘

┌──────────────────┐       ┌────────────────────┐
│  ksf_hrm_benefits│       │ ksf_hrm_emergency_│
├──────────────────┤       │     contacts       │
│ id (PK)          │       ├────────────────────┤
│ code (UNIQUE)    │       │ id (PK)            │
│ name             │       │ employee_id (FK)   │
│ type             │       │ name               │
│ employer_rate    │       │ relationship       │
│ employee_rate    │       │ phone              │
│ gl_code_expense  │       └────────────────────┘
│ gl_code_liability│       ┌────────────────────┐
└──────────────────┘       │ksf_hrm_dependents  │
                           ├────────────────────┤
                           │ id (PK)            │
                           │ employee_id (FK)   │
                           │ first_name         │
                           │ relationship       │
                           │ date_of_birth      │
                           └────────────────────┘
```

### 3.2 Table Indexing Strategy

| Table | Primary Key | Unique Index | Regular Indexes |
|-------|-------------|--------------|-----------------|
| ksf_hrm_employees | id | employee_number | email, status, department |
| ksf_hrm_grades | id | code | - |
| ksf_hrm_benefits | id | code | - |
| ksf_hrm_compensation | id | - | employee_id, grade_id |
| ksf_hrm_emergency_contacts | id | - | employee_id |
| ksf_hrm_dependents | id | - | employee_id |
| ksf_hrm_payroll | id | - | employee_id, period |

---

## 4. Integration Architecture

### 4.1 FrontAccounting Integration

#### Hook System
- `ksf_fa_hrm_install` - Triggered on module installation
- Extends FA hook system for custom lifecycle events

#### Permission System
- Uses FA's permission model with custom permission `ksf_hrm`
- Permission level: FA_PERMISSION_READ

#### Menu System
- Adds HRM menu item via `add_module_extensions_menu_item()`

### 4.2 GL Integration

```
Payroll Process Flow:
┌──────────────┐    ┌──────────────────┐    ┌────────────┐
│ Compensation ���───>│ PayrollGLentries │───>│   FA_GL   │
│   Service    │    │ createJournalEntry│    │  Journal  │
└──────────────┘    └──────────────────┘    └────────────┘
                           │
                           v
                    ┌──────────────────┐
                    │ksf_hrm_payroll  │
                    │   (tracking)    │
                    └──────────────────┘
```

### 4.3 Import System Integration

```
CSV Import Flow:
┌─────────┐    ┌────────────────┐    ┌────────────────┐
│ Upload  │───>│ Field Mapping  │───>│ Process/Upsert │
│  File   │    │   (ksf_DataIO) │    │    to DB       │
└─────────┘    └────────────────┘    └────────────────┘
```

---

## 5. Design Patterns

### 5.1 Service Layer Pattern

```
PayrollGLentries.php
├── uses CompensationService from ksf-hrm library
├── creates JournalEntry objects
└── delegates GL posting to FA write_journal()
```

### 5.2 Hook Pattern

```
InstallHook.php
├── install() - main entry point
├── createTables() - database setup
├── createMenuItems() - navigation
└── setDefaultPreferences() - configuration
```

### 5.3 Factory Pattern

```
import.php
├── returns configuration array
└── processor function handles instantiation
```

---

## 6. Configuration Management

### 6.1 Company Preferences

Stored in FA's company preferences table:

```php
$defaults = [
    'ksf_salary_expense_gl' => 'G01',  // Default salary GL
    'ksf_ot_expense_gl' => 'O01',       // Default overtime GL
    'ksf_year_hours' => '2080',         // Annual work hours
    'ksf_week_hours' => '40',           // Weekly hours
    'ksf_ot_enabled' => '1',           // Overtime enabled
];
```

### 6.2 GL Code Mapping

Flexible mapping stored per company:
- Salary Expense: G01
- Overtime Expense: O01
- EI/CPP/Pension/Health: Various 2xxx accounts

---

## 7. Error Handling

### 7.1 Database Errors
- Table creation failures: Logged with specific table name
- Query failures: Returned to caller with error message

### 7.2 Import Errors
- Missing required fields: Skip row, continue processing
- Upsert failures: Log emp_no, continue

### 7.3 GL Posting Errors
- Journal entry failures: Return boolean, log details

---

## 8. Security Considerations

### 8.1 Input Validation
- All database queries use `db_escape()` for SQL injection prevention
- Field mapping validates target field names

### 8.2 Access Control
- Menu access controlled by FA permission system
- Company-level data isolation via FA's company filter

### 8.3 Data Privacy
- Employee data includes PII (SIN, bank details) - should be protected
- Salary data restricted by permission levels

---

## 9. Extension Points

### 9.1 Custom Benefits
- New benefit types can be added to ksf_hrm_benefits
- GL codes configurable per benefit

### 9.2 Custom Fields
- Employee table can be extended via separate metadata table
- Import can be extended for additional fields

### 9.3 Payroll Processing
- PayrollGLentries class can be extended for custom journal entry formats
- GL code mapping is extensible

---

## 10. Dependencies

### 10.1 External Packages

```json
{
    "require": {
        "php": ">=8.1",
        "ksfraser/ksf-hrm": "^1.0"
    }
}
```

### 10.2 Required Features

- FrontAccounting 2.4+ hook system
- MySQL 5.7+ with InnoDB
- PHP 8.1+ with PDO

---

## 11. Performance Considerations

### 11.1 Database Optimization
- Indexed columns for common queries (employee_id, status, department)
- Limited denormalization for performance

### 11.2 Import Performance
- Batch processing of rows
- Pre-check for existence before insert/update

### 11.3 Caching
- GL code mapping cached in memory during session
- Company preferences cached per page load
