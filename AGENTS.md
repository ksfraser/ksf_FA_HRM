# AGENTS.md - ksf_FA_HRM

## Overview

FA Module for Human Resources Management - org hierarchy (departments, teams, roles, positions), employee management via CRM contacts, compensation, payroll, benefits, and leave types admin.

### Core Principles
- SOLID, DRY, TDD, DI, SRP

## Namespace Convention

- **FA Platform modules**: `ksfraser\FrontAccounting\<ModuleName>\`
- **Current**: `ksfraser\FrontAccounting\HRM\`
- **Directory**: `src/` with PSR-4 autoload

```json
"autoload": {
    "psr-4": {
        "ksfraser\\FrontAccounting\\HRM\\": "src/"
    }
}
```

## Table Ownership

### HRM Core Tables (`0_hrm_*`)
| Table | Purpose |
|-------|---------|
| `0_hrm_departments` | Department hierarchy (parent_id recursion) |
| `0_hrm_teams` | Teams within departments (recursive via parent_team_id) |
| `0_hrm_role_dictionary` | Global master list of role types (20 seeded) |
| `0_hrm_roles` | Department-scoped roles (cloned from dictionary) |
| `0_hrm_positions` | Positions with auto-generated DEPT-TEAM-### codes |
| `0_hrm_grades` | Salary grades with min/max ranges |
| `0_hrm_contacts_employment` | Core employee record (FK to 0_crm_persons) |
| `0_hrm_employment_status` | Status lookup (Active, Probation, etc.) |

### Compensation & Payroll Tables
| Table | Purpose |
|-------|---------|
| `0_hrm_work_assignments` | Links employee to position + salary + grade |
| `0_hrm_pay_rate_history` | Salary change audit trail (effective_date-based) |
| `0_hrm_pay_periods` | Pay period definitions |
| `0_hrm_pay_elements` | Earnings, deductions, contributions |
| `0_hrm_salary_structure` | Links grades to pay elements |
| `0_hrm_separation_reasons` | Termination reason lookup |
| `0_hrm_benefits` | Benefit definitions |
| `0_hrm_employee_benefits` | Employee benefit assignments |
| `0_hrm_payroll` | Payroll run records |
| `0_hrm_payroll_entries` | Payroll line items |

### Employee Detail Tables (`0_hrm_*`)
| Table | Purpose |
|-------|---------|
| `0_hrm_contacts_pii` | PII data (DOB, national ID, tax number) |
| `0_hrm_contacts_banking` | Bank account details |
| `0_hrm_dependent_details` | Employee dependents |

### NOT Owned by HRM
- **Leave tables** → `ksf_FA_Leave` module (`0_leave_*`)
- **Recruitment tables** → `ksf_FA_Recruitment` module (`0_recruit_*`)
- **FA security roles** → `0_security_roles` (access control only)
- **RBAC teams** → `ksf_RBAC` module (record-level access control)

## Org Hierarchy

```
Departments
  └── Teams (recursive, team_code used in position code)
        └── Roles (from global dictionary, cloned per dept)
              └── Positions (DEPT-TEAM-###, e.g., IT-SUP-001)
```

## Repository Structure

```
ksf_FA_HRM/
├── sql/
│   └── install.sql          # All HRM tables (0_ prefix)
├── includes/
│   ├── employee_db.inc      # Employee/department/position/grade CRUD
│   ├── payroll_db.inc       # Payroll queries
│   └── leave_db.inc         # Leave balance queries
├── src/
│   ├── Hooks/
│   │   └── InstallHook.php  # Module lifecycle
│   └── GL/
│       └── PayrollGLentries.php  # GL posting
├── pages/
│   ├── employees.php        # Employee list + add/edit
│   ├── departments.php      # Org hierarchy
│   ├── positions.php        # Position management
│   ├── grades.php           # Grade management
│   ├── payroll.php          # Payroll view
│   ├── benefits.php         # Benefits management
│   ├── leave.php            # Leave balances view
│   ├── leave_types.php      # Leave types admin
│   ├── recruitment.php      # Recruitment placeholder
│   └── reports.php          # Reports placeholder
├── hooks.php
├── index.php
├── composer.json
└── ProjectDcs/
```

## Dependencies

- FrontAccounting 2.4+ (core)
- ksf_FA_CRM (contact system - 0_crm_persons)
- PHP >=7.3
