# FrontAccounting HR Management Module

**ksf_FA_HRM** - Human Resources Management module for FrontAccounting

## Overview

Comprehensive HRM module that extends FrontAccounting with employee management, payroll GL integration, and HR workflows. Integrates with `ksfraser/ksf-hrm` library for enhanced HR functionality.

## Features

- **Employee Management**
  - Full CRUD operations for employee master records
  - Comprehensive employee data fields (personal info, employment details, organizational assignments)
  - Employee status tracking (Active, Inactive, Terminated)
  - Manager and team assignments

- **Compensation Management**
  - Grade-based salary structures with min/max ranges
  - Hourly rate support with overtime eligibility
  - Salary and hourly compensation tracking
  - Employee type classification (Salary/Hourly)

- **Benefits Administration**
  - Configurable benefit plans with employer/employee contribution rates
  - Fixed amount and percentage-based benefits
  - Tax deductible benefits tracking
  - Mandatory benefits support (EI, CPP, etc.)
  - GL code mapping for expense and liability accounts

- **Payroll Integration**
  - General Ledger posting for payroll entries
  - Automatic journal entry creation
  - Support for salary, overtime, vacation, sick time expenses
  - Employer liability tracking (EI, CPP, Pension, Health)

- **Employee Data Management**
  - Emergency contact information
  - Dependent/beneficiary tracking
  - Document attachment support via ksf_Documents

- **CSV Import/Export**
  - CSV employee import with field mapping
  - Upsert capability (create new or update existing)
  - Auto-mapping of common field names

- **Configuration**
  - Default GL codes for various payroll expense types
  - Configurable work hours (weekly, yearly)
  - Overtime settings

## Quick Start

```bash
# 1. Install the library
composer require ksfraser/ksf-hrm

# 2. Copy module to FA
cp -r ksf_FA_HRM /path/to/frontaccounting/modules/

# 3. Activate via Admin → Modules
```

## Module Installation

The module is installed via FrontAccounting's extension installer:

1. Navigate to: **Admin → Module Administration**
2. Find **ksf_FA_HRM** in the available extensions
3. Click **Install** to activate

On installation, the module will:
- Create all required database tables
- Add HRM menu items
- Set default company preferences

## Database Tables

### Core Tables

| Table | Description |
|-------|-------------|
| `ksf_hrm_employees` | Employee master records |
| `ksf_hrm_grades` | Salary grades with min/max ranges |
| `ksf_hrm_benefits` | Benefit plan definitions |
| `ksf_hrm_compensation` | Employee compensation records |
| `ksf_hrm_payroll` | Payroll journal entries tracking |

### Related Tables

| Table | Description |
|-------|-------------|
| `ksf_hrm_emergency_contacts` | Employee emergency contacts |
| `ksf_hrm_dependents` | Employee dependents/beneficiaries |

### Table Schemas

#### ksf_hrm_employees
```sql
- id: INT PRIMARY KEY
- employee_number: VARCHAR(50) UNIQUE
- first_name: VARCHAR(100)
- last_name: VARCHAR(100)
- email: VARCHAR(150)
- phone: VARCHAR(30)
- department: VARCHAR(100)
- job_title: VARCHAR(100)
- status: VARCHAR(20) DEFAULT 'Active'
- hire_date: DATE
- termination_date: DATE
- manager_id: INT (self-reference)
- career_manager_id: INT
- operations_manager_id: INT
- team_id: INT
- created_at: TIMESTAMP
- updated_at: TIMESTAMP
```

#### ksf_hrm_grades
```sql
- id: INT PRIMARY KEY
- code: VARCHAR(20) UNIQUE
- name: VARCHAR(100)
- min_salary: DECIMAL(12,2)
- max_salary: DECIMAL(12,2)
- min_hourly: DECIMAL(10,4)
- max_hourly: DECIMAL(10,4)
- description: TEXT
- level: VARCHAR(20)
- active: TINYINT DEFAULT 1
```

#### ksf_hrm_benefits
```sql
- id: INT PRIMARY KEY
- name: VARCHAR(100)
- code: VARCHAR(20) UNIQUE
- type: VARCHAR(50)
- employer_rate: DECIMAL(5,2)
- employee_rate: DECIMAL(5,2)
- fixed_amount: DECIMAL(10,2)
- calculation_period: VARCHAR(20)
- is_percentage_based: TINYINT
- gl_code_expense: VARCHAR(20)
- gl_code_liability: VARCHAR(20)
- provider: VARCHAR(100)
- is_mandatory: TINYINT
- is_tax_deductible: TINYINT
- active: TINYINT
```

#### ksf_hrm_compensation
```sql
- id: INT PRIMARY KEY
- employee_id: INT FOREIGN KEY
- grade_id: INT FOREIGN KEY
- percent_of_grade: DECIMAL(5,2)
- annual_salary: DECIMAL(12,2)
- hourly_rate: DECIMAL(10,4)
- employee_type: VARCHAR(20)
- effective_date: DATE
- end_date: DATE
- ot_eligible: TINYINT
- ot_multiplier: DECIMAL(3,2)
- gl_code_salary: VARCHAR(20)
- gl_code_overtime: VARCHAR(20)
- bonus_target: DECIMAL(12,2)
```

## Company Preferences

The module sets the following default preferences:

| Preference | Default | Description |
|------------|---------|-------------|
| `ksf_salary_expense_gl` | G01 | Salary expense GL code |
| `ksf_ot_expense_gl` | O01 | Overtime expense GL code |
| `ksf_year_hours` | 2080 | Annual work hours |
| `ksf_week_hours` | 40 | Weekly work hours |
| `ksf_ot_enabled` | 1 | Overtime enabled |

Additional configurable GL codes:
- `ksf_vacation_expense_gl` - Vacation expense
- `ksf_sick_expense_gl` - Sick leave expense
- `ksf_ei_expense_gl` - Employment Insurance expense (2200)
- `ksf_cpp_expense_gl` - CPP expense (2210)
- `ksf_ei_liability_gl` - EI liability (2300)
- `ksf_cpp_liability_gl` - CPP liability (2310)
- `ksf_pension_expense_gl` - Pension expense (2400)
- `ksf_pension_liability_gl` - Pension liability (2410)
- `ksf_health_expense_gl` - Health benefit expense (2500)
- `ksf_health_liability_gl` - Health benefit liability (2510)

## Permissions

| Permission | Code | Description |
|------------|------|-------------|
| HRM Access | `ksf_hrm` | View HRM module |
| HRM_VIEW_EMP | (via FA) | View employee records |
| HRM_MANAGE_EMP | (via FA) | Create/edit employees |
| HRM_VIEW_SALARY | (via FA) | View salary data |
| HRM_MANAGE_SALARY | (via FA) | Manage salary |

## Integration Points

- **FA_GL** - Payroll posting to general ledger
- **ksf_Documents** - Employee document storage
- **ksf_FA_Timesheets** - Time tracking integration
- **ksfraser/ksf-hrm** - Core HRM library

## API

### Employee Functions

```php
// Get all active employees
$employees = get_all_employees();

// Get single employee by ID
$emp = get_employee($id);

// Create employee
create_employee($data);

// Update employee  
update_employee($id, $data);

// Delete (deactivate) employee
delete_employee($id);

// Search employees
search_employees($criteria);
```

### Compensation Functions

```php
// Get employee compensation
$comp = get_employee_compensation($employee_id);

// Set employee compensation
set_employee_compensation($employee_id, $compensation_data);
```

### Payroll Functions

```php
// Get GL code mapping
$glMapping = get_gl_code_mapping();

// Create payroll journal entry
$journalEntry = create_payroll_journal($employee_id, $gl_entries, $description);

// Post payroll to GL
post_payroll_to_gl($journal_entry);
```

### Import Functions

```php
// Initialize HRM import
ksf_render_hrm_import('employee');

// Process import row
ksf_process_employee_import($row);
```

## File Structure

```
ksf_FA_HRM/
├── includes/
│   └── import.php              # CSV import functionality
├── src/
│   ├── Hooks/
│   │   └── InstallHook.php    # Installation/uninstall hooks
│   └── GL/
│       └── PayrollGLentries.php # GL integration for payroll
├── sql/
│   └── install.sql            # SQL installation scripts
├── ProjectDcs/
│   ├── Architecture.md        # Technical architecture
│   ├── Functional Requirements.md
│   ├── Test Plan.md
│   ├── UAT Plan.md
│   ├── Use Case.md
│   ├── Business Requirements.md
│   └── RTM.md
├── install.php                # Module installer
├── import.php                 # Import configuration
├── composer.json
└── README.md
```

## Requirements

- FrontAccounting 2.4+
- PHP 8.1+
- `ksfraser/ksf-hrm` package

## Directory Structure

```
/home/kevin/Documents/ksf_FA_HRM/
```

## License

GPL-3.0
