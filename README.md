# FrontAccounting HR Management Module

**ksf_FA_HRM** - Human Resources Management module for FrontAccounting

```
Package: ksf_FA_HRM
Name: ksf_FA_HRM
Description: HRM module for FrontAccounting
Author: Kevin Fraser <kevin@ksfraser.com>
Maintenance: Kevin Fraser <kevin@ksfraser.com>
Homepage: http://ksfraser.com
Type: extension
InstallPath: modules/ksf_FA_HRM
Filename: ksf_FA_HRM
Version: 2.4.3-1
FA-Version: 2.4
PHP-Version: 7.3
```

## Routing

The whole application is served by **one** front controller:

```
modules/ksf_FA_HRM/hrm.php
```

`index.php` and `pages/*.php` are forwarder shims kept for one release so
existing bookmarks keep working. They set `$_GET['page']`, rewrite
`$_SERVER['PHP_SELF']`/`REQUEST_URI` to `hrm.php` and then `require` it
internally — no redirect, so an in-flight POST keeps its body.

### URL shape

| URL | Meaning |
|-----|---------|
| `hrm.php` | default tab (`employees`) |
| `hrm.php?page=<tab>` | read a tab |
| `hrm.php?page=<tab>&filter_<x>=…` | read a tab with a filter applied |
| `hrm.php?page=<tab>&action=edit&id=N` | pre-load the entry form for record N |
| `hrm.php?page=<tab>&action=save` | create / update — **POST only** |
| `hrm.php?page=<tab>&action=delete&id=N` | delete record N — **POST only** |

`action=delete` is POST-only on purpose: a GET-addressable delete is a
cross-site request forgery. The summary table's own Delete buttons are POST
submit buttons, so they are the only reachable path.

### Why `page` carries the tab *and* the pager page number

`AbstractTabController` reads `$_GET['page']` for paging, and
`MasterSummaryTable::pagerUrl()` writes `?page=<n>` into its pager links. The
tab selector therefore cannot *also* be `page` without colliding, so `hrm.php`
resolves by rule:

1. a `page` value that names a **registered tab key** is the tab;
2. anything else (a number) is the **pager position**, and the tab comes from
   `view`;
3. neither → the shell's default view.

Pager links are emitted as `?page=<n>&view=<tab>` and resolve cleanly through
that rule. `?view=<tab>` is still accepted, so pre-1.1.0 bookmarks work.

### Tabs

| Tab key | Security area | Controller |
|---------|---------------|------------|
| `employees` | `SA_HRM_EMPLOYEE` | `EmployeesTabController` |
| `departments` | `SA_HRM_DEPARTMENT` | `DepartmentsTabController` |
| `positions` | `SA_ksf_FA_HRMMANAGE` | `PositionsTabController` |
| `grades` | `SA_ksf_FA_HRMMANAGE` | `GradesTabController` |
| `roles` | `SA_HRM_ROLES` | `RolesTabController` |
| `teams` | `SA_HRM_TEAMS` | `TeamsTabController` |
| `payroll` | `SA_HRM_PAYROLL` | `PayrollTabController` |
| `benefits` | `SA_HRM_BENEFITS` | `BenefitsTabController` |
| `leave` | `SA_HRM_LEAVE` | `LeaveTabController` |
| `leave_types` | `SA_HRM_LEAVE` | `LeaveTypesTabController` |
| `recruitment` | `SA_HRM_RECRUITMENT` | `RecruitmentTabController` |
| `reports` | `SA_ksf_FA_HRMVIEW` | `ReportsTabController` |

`recruitment` is a **placeholder**: the module ships no vacancy / candidate /
application table, so the tab renders its full structure and refuses mutations
explicitly rather than pretending to store them.

## Adding an HRM tab from another module

The app id is **`hrm`**, which is what derives the register-with-me hook name:

```
hrm_register_tabs
```

`HrmAppShell::boot()` fires `hook_invoke_all('hrm_register_tabs', $data)` after
the FA session is up, and merges everything responders push into `$data['tabs']`
into the menu and dispatch. So another module adds an HRM tab by exposing a
method with exactly that name on its FA hooks class:

```php
<?php
define('SS_ksf_FA_Timesheets', 118 << 8);

class hooks_ksf_FA_Timesheets extends hooks
{
    var $module_name = 'ksf_FA_Timesheets';
    var $version     = '2.4.3-1';

    /**
     * Contribute a tab to the HRM application shell.
     *
     * Anything appended to $data['tabs'] is merged into the HRM menu and is
     * reachable at hrm.php?page=<key>.
     *
     * @param array $data  Hook payload; append to $data['tabs'] by reference.
     * @param mixed $opts  Unused, part of the FA hook signature.
     *
     * @return void
     */
    public function hrm_register_tabs(&$data, $opts = null)
    {
        $data['tabs'][] = array(
            'key'              => 'timesheets',        // hrm.php?page=timesheets
            'label'            => _('Timesheets'),
            'security'         => 'SA_TIMESHEETS',    // your module's FA area
            'controller_class' => \ksfraser\FrontAccounting\Timesheets\Controller\TimesheetsTabController::class,
            'priority'         => 40,                  // lower = earlier in the menu
            'order'            => 0,                   // tie-break within a priority
        );
    }
}
```

### Requirements on the contributed tab

| Field | Required | Notes |
|-------|----------|-------|
| `key` | yes | Unique across the app; it is the `?page=` value. |
| `label` | yes | Already translated by the responder. |
| `security` | yes | An FA security area from **your** `install_access()`. `hrm.php` falls back to `SA_ksf_FA_HRMVIEW` for the pre-session check and re-checks this area after `boot()`. |
| `controller_class` | one of | FQCN of an `AbstractTabController` (or `FilteredTabController`) subclass, **or** |
| `page_file` | one of | Absolute path to a procedural fragment included inside the shell's already-open `page()`/`end_page()` — it must **not** call `page()`/`end_page()` itself and must **not** redefine `$path_to_root`. |
| `priority` / `order` | no | Defaults `50` / `0`. |

You may pass a `TabRegistration` instance instead of an array, or use
`ksfraser\FrontAccounting\Common\Traits\TabRegistrationTrait`:

```php
use ksfraser\FrontAccounting\Common\Traits\TabRegistrationTrait;

class hooks_ksf_FA_Timesheets extends hooks
{
    use TabRegistrationTrait;

    public function hrm_register_tabs(&$data, $opts = null)
    {
        $this->respondToAppRegister(
            $data,
            'hrm',                                                  // app id
            'timesheets',                                            // tab key
            _('Timesheets'),                                         // label
            'SA_TIMESHEETS',                                         // security area
            TimesheetsTabController::class,                          // controller
            40,                                                      // priority
            0                                                        // order
        );
    }
}
```

`TabRegistrationTrait` also records the registration in the generic
`ExtensionRegistry` under `hrm_tabs`, so hosts can query registrations without
firing the hook again.

### Lifecycle

1. `hrm.php` constructs `HrmAppShell`, which registers the twelve core tabs
   **in its constructor** — that is deliberate, because `$page_security` has to
   be resolved *before* `session.inc` and the hook needs a session.
2. `session.inc` + `add_access_extensions()`.
3. `$appShell->boot()` fires `hrm_register_tabs` and merges the responders.
4. `hrm.php` re-resolves the view, so a tab that only appeared at step 3 is
   still reachable by direct `?page=` URL.
5. `page()` → `renderMenu()` → `dispatch($view)` → the controller's `run()`.

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
├── hrm.php                     # SINGLE front controller (?page= / ?action=)
├── index.php                   # forwarder shim -> hrm.php
├── pages/                      # forwarder shims -> hrm.php (deprecated)
├── includes/
│   └── import.php              # CSV import functionality
├── src/
│   ├── App/
│   │   └── HrmAppShell.php     # app shell: core tabs + hrm_register_tabs hook
│   ├── Controller/             # one *TabController per tab
│   ├── Hooks/
│   │   └── InstallHook.php     # Installation/uninstall hooks
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
- PHP 7.3+ (the container runtime is 7.4.33; no PHP 8 syntax is used)
- `ksfraser/ksf-fa-common` (`AbstractAppShell`, `AbstractTabController`,
  `TabRegistration`, `TabRegistrationTrait`)
- `ksfraser/html` (`MasterSummaryTable`, `FormFooter`, `TabContext`)

## Directory Structure

```
/home/kevin/Documents/ksf_FA_HRM/
```

## License

GPL-3.0
