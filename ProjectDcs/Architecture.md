# Architecture - ksf_FA_HRM

## 1. Overview

ksf_FA_HRM is the FrontAccounting adapter for Human Resources Management. It provides the FA UI layer, database tables, and hooks to integrate HRM with FrontAccounting.

## 2. Design Principles

- SOLID, DRY, TDD, DI, SRP
- FA 2.4 module conventions (hooks class, gzip config, db_escape)
- PSR-4 autoloading under `ksfraser\FrontAccounting\HRM\`

## 3. Data Architecture

### 3.1 Entity Relationship Diagram

```
                    ┌──────────────────────┐
                    │   0_crm_persons      │  (FA built-in)
                    │   id, name, email    │
                    └─────────┬────────────┘
                              │ person_id FK
                    ┌─────────▼────────────┐
                    │ 0_hrm_contacts_      │
                    │   employment         │
                    │ employment_id (PK)   │
                    │ person_id (UNIQUE)   │
                    │ department_id ───────┤──┐
                    │ position_id ─────────┤──┤──┐
                    │ grade_id ────────────┤──┤──┤──┐
                    │ hire_date            │  │  │  │
                    │ salary_amount        │  │  │  │
                    └─────────────────────┘  │  │  │
                                             │  │  │
         ┌───────────────────────────────────┘  │  │
         ▼                                      │  │
┌────────────────────┐                         │  │
│ 0_hrm_departments  │◄────────────────────────┘  │
│ department_id (PK) │                            │
│ department_name    │                            │
│ parent_dept_id ────┤── (self-ref)               │
│ manager_person_id  │                            │
└─────────┬──────────┘                            │
          │ department_id                         │
    ┌─────▼──────────┐                            │
    │ 0_hrm_teams    │                            │
    │ team_id (PK)   │                            │
    │ department_id  │                            │
    │ parent_team_id │◄── (self-ref)              │
    │ team_code      │  (used in position code)   │
    └─────┬──────────┘                            │
          │ team_id                               │
    ┌─────▼──────────┐    ┌──────────────────┐   │
    │ 0_hrm_positions│◄───│ 0_hrm_roles      │   │
    │ position_id PK │    │ role_id (PK)     │   │
    │ position_code  │    │ department_id    │   │
    │ department_id  │    │ role_dict_id ────┼───┘
    │ team_id ───────┤    │ role_name        │
    │ role_id ───────┘    └──────────────────┘
    │                   ▲
    └───────────────────┘
          │ position_id FK
    ┌─────▼──────────────┐
    │ 0_hrm_grades       │◄──── 0_hrm_work_assignments
    │ grade_id (PK)      │      (employment_id + position_id
    │ grade_name         │       + grade_id + salary)
    │ min_salary         │
    │ max_salary         │      ┌─────────────────────────┐
    └────────────────────┘      │ 0_hrm_pay_rate_history  │
                                │ rate_id (PK)            │
                                │ employment_id           │
                                │ old_salary, new_salary  │
                                │ effective_date          │
                                └─────────────────────────┘
```

### 3.2 Table Indexing Strategy

| Table | Primary Key | Unique | Foreign Keys | Indexes |
|-------|-------------|--------|--------------|---------|
| `0_hrm_departments` | `department_id` | — | `parent_dept_id` → self, `manager_person_id` → `0_crm_persons.id` | `parent_dept_id` |
| `0_hrm_teams` | `team_id` | — | `department_id` → `0_hrm_departments`, `parent_team_id` → self | `department_id`, `parent_team_id` |
| `0_hrm_role_dictionary` | `dict_id` | `role_name` | — | `role_name` |
| `0_hrm_roles` | `role_id` | — | `department_id` → `0_hrm_departments`, `role_dict_id` → `0_hrm_role_dictionary` | `department_id`, `role_dict_id` |
| `0_hrm_positions` | `position_id` | `position_code` | `department_id` → `0_hrm_departments`, `team_id` → `0_hrm_teams`, `role_id` → `0_hrm_roles` | `department_id`, `team_id`, `role_id` |
| `0_hrm_grades` | `grade_id` | `grade_name` | — | `grade_name` |
| `0_hrm_contacts_employment` | `employment_id` | `person_id` | `person_id` → `0_crm_persons.id`, `department_id` → `0_hrm_departments`, `position_id` → `0_hrm_positions`, `grade_id` → `0_hrm_grades`, `status_id` → `0_ksf_hrm_employment_status` | `person_id`, `department_id`, `position_id`, `grade_id` |
| `0_ksf_hrm_employment_status` | `status_id` | `status_name` | — | `status_name` |
| `0_hrm_work_assignments` | `assignment_id` | — | `employment_id` → `0_hrm_contacts_employment`, `position_id` → `0_hrm_positions`, `grade_id` → `0_hrm_grades` | `employment_id`, `position_id`, `grade_id` |
| `0_hrm_pay_rate_history` | `rate_id` | — | `employment_id` → `0_hrm_contacts_employment` | `employment_id`, `effective_date` |
| `0_hrm_pay_periods` | `period_id` | — | — | `period_start_date`, `status` |
| `0_hrm_pay_elements` | `element_id` | `element_name` | — | `element_type` |
| `0_hrm_salary_structure` | `structure_id` | — | `grade_id` → `0_hrm_grades`, `element_id` → `0_hrm_pay_elements` | `grade_id`, `element_id` |
| `0_hrm_separation_reasons` | `reason_id` | `reason_name` | — | `reason_name` |
| `0_ksf_hrm_benefits` | `benefit_id` | `benefit_name` | — | `benefit_name` |
| `0_ksf_hrm_employee_benefits` | `emp_benefit_id` | — | `employment_id` → `0_hrm_contacts_employment`, `benefit_id` → `0_ksf_hrm_benefits` | `employment_id`, `benefit_id` |
| `0_ksf_hrm_payroll` | `payroll_id` | — | `period_id` → `0_hrm_pay_periods` | `period_id`, `status` |
| `0_ksf_hrm_payroll_entries` | `entry_id` | — | `payroll_id` → `0_ksf_hrm_payroll`, `employment_id` → `0_hrm_contacts_employment`, `element_id` → `0_hrm_pay_elements` | `payroll_id`, `employment_id`, `element_id` |
| `0_hrm_contacts_pii` | `pii_id` | `employment_id` | `employment_id` → `0_hrm_contacts_employment` | `employment_id` |
| `0_hrm_contacts_banking` | `banking_id` | — | `employment_id` → `0_hrm_contacts_employment` | `employment_id` |
| `0_hrm_dependent_details` | `dependent_id` | — | `employment_id` → `0_hrm_contacts_employment` | `employment_id` |

## 4. Module Architecture

### 4.1 Class Structure

```
ksfraser\FrontAccounting\HRM\
├── Hooks\
│   └── InstallHook.php      # Module lifecycle hooks
└── GL\
    └── PayrollGLentries.php  # GL posting for payroll
```

### 4.2 GL Integration

Payroll GL entries are posted via `PayrollGLentries` class. GL account codes are configurable per company. Payroll runs use the `0_ksf_hrm_payroll` table to track status (Open → Processing → Closed → Paid).

### 4.3 FA Module Integration

- **Menu**: FAModuleMenu sidebar with 10 views
- **Access Control**: SA_HRM_* security areas
- **Config**: Gzip-compressed `_init/config`
- **Hooks**: Class-based `hooks_ksf_FA_HRM` extending FA `hooks`

## 5. Org Hierarchy

```
Departments
  └── Teams (recursive, team_code used in position code)
        └── Roles (from global dictionary, cloned per dept)
              └── Positions (DEPT-TEAM-###, e.g., IT-SUP-001)
```

Positions use auto-generated codes: `{DEPT_CODE}-{TEAM_CODE}-{SEQ}`.

## 6. Dependencies

- FrontAccounting 2.4+ (core)
- ksf_FA_CRM (contact system - 0_crm_persons)
- PHP >=7.3
