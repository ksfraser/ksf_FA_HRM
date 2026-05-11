# Business Requirements - ksf_FA_HRM

## Overview
ksf_FA_HRM is the FrontAccounting adapter for ksf_HRM (Human Resource Management). It provides the FA UI layer, database tables, and hooks to integrate the HRM module with FrontAccounting.

## Relationship to Core Module

### Core Module
- **ksf_HRM**: Business logic layer (entity classes, services, repositories)
- Namespace: `Ksfraser\HRM`
- Published as: `ksfraser/ksf-hrm`

### FA Adapter
- **ksf_FA_HRM**: FrontAccounting presentation and persistence layer
- Namespace: `Ksfraser\FA\HRM`
- Integrates with FA core (dimensions, users, bank accounts)

## FA-Specific Features

### Database Integration
- FA-compliant table naming: `fa_hrm_employees`, `fa_hrm_compensation`, etc.
- Foreign keys to FA core tables: `fa_gl_setup`, `fa_bank_accounts`
- Dimensions for reporting (ksf_Dimensions or FA dimensions)

### User Integration
- Uses FA user authentication
- Links employees to FA users where applicable
- Permissions via FA access levels

### Financial Integration
- Bank account integration (FA bank accounts)
- GL integration for payroll liabilities
- Tax form generation (T4, T4A)

### UI Integration
- FA menu entries
- FA theme/styling
- FA form handling and validation
- Extension of FA customer/supplier forms for employee types

## Link to Core Business Requirements

This adapter implements the requirements defined in ksf_HRM/ProjectDcs/Business Requirements.md, specifically:

1. **Employee Record Management** - FA database tables and forms
2. **Banking & Tax Information** - FA bank account integration
3. **Emergency & Family** - HRM-specific tables
4. **Compensation Management** - GL integration for payables
5. **Benefits Administration** - HRM-specific tables

## Out of Scope for FA Adapter
- Core business logic (handled by ksf_HRM)
- Standalone deployment (requires FA)

## Dependencies
- FrontAccounting core
- ksf_HRM (core module)
- ksf_Workflow (for approvals)
- ksf_Leave (FA integration)
- ksf_Timesheets (FA integration)

## Reference
- Core BR: `/home/kevin/Documents/ksf_HRM/ProjectDcs/Business Requirements.md`
- Core UC: `/home/kevin/Documents/ksf_HRM/ProjectDcs/Use Case.md`

*Document Version: 1.0.0*
*Last Updated: 2026-05-11*