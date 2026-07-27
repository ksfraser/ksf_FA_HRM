# Business Requirements - ksf_FA_HRM

## Overview
ksf_FA_HRM is the FrontAccounting adapter for Human Resources Management. It provides the FA UI layer, database tables, and hooks to integrate HRM with FrontAccounting.

## Module Relationships

### What HRM Provides
- **Org Hierarchy**: Departments → Teams → Roles → Positions
- **Employee Management**: Employee records linked to CRM contacts
- **Compensation**: Grades, salary ranges, work assignments, pay rate history
- **Payroll**: Pay periods, pay elements, salary structure, payroll runs
- **Benefits**: Benefit definitions and employee benefit assignments
- **Leave Types Admin**: Manages leave type definitions (actual leave tracking is in ksf_FA_Leave)

### What HRM Does NOT Own
- **Leave tracking** (requests, balances, approvals, banks) → `ksf_FA_Leave`
- **Recruitment** (job openings, applications, interviews, offers) → `ksf_FA_Recruitment`
- **Access control roles** (FA security roles, RBAC teams) → FA core / `ksf_RBAC`

## FA-Specific Features

### Database Integration
- HRM-compliant table naming: `0_hrm_*` prefix
- Foreign keys to FA core: `0_crm_persons` (employees are CRM contacts)
- Standard FA `db_escape()` for all queries

### User Integration
- Employees linked to FA users via `login_id` field
- Menu security via FA access areas (SA_HRM_*)

### Financial Integration
- GL integration for payroll via `PayrollGLentries`
- Configurable GL code mapping per company
- Pay period tracking with Open/Processing/Closed/Paid statuses

### UI Integration
- FA app tab with sidebar menu (FAModuleMenu)
- 10 views: Employees, Departments, Positions, Grades, Payroll, Benefits, Leave, Leave Types, Recruitment, Reports
- Add/Edit forms with dynamic dropdowns

## Dependencies
- FrontAccounting core (2.4+)
- ksf_FA_CRM (contact system - employees are CRM persons)
- PHP >=7.3

## Out of Scope
- Core business logic (planned for ksfraser\HRM namespace library)
- Standalone deployment (requires FA)

*Document Version: 2.0.0*
*Last Updated: 2026-07-27*
