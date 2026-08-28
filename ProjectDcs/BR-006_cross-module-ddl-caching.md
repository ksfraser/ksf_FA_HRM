# BR-006 — Cross-Module DDL Caching Architecture

**Modules:** ksf_FA_HRM, ksf_FA_CRM, ksf_FA_ProjectManagement
**Status:** IMPLEMENTED (DepartmentService)

## Business Need

Multiple FA modules render dropdown lists (DDLs) for shared reference data:
departments, teams, roles, grades, positions, employment statuses, customer
types, territories, project types, and similar lookup entities. Each module
currently queries the database independently for the same reference data,
resulting in redundant DB round-trips within a single page request and across
modules on the same page.

Reference data (departments, grades, role dictionaries, etc.) changes
infrequently — typically only during initial setup or periodic organizational
restructuring. The business requires that this stable reference data be
cached and shared across modules without each module maintaining its own
copy or hitting the database repeatedly.

## Business Requirement

The business requires:

1. A centralized caching layer for reference data DDLs, owned by the module
   that defines the entity (e.g., HRM owns departments, CRM owns territories).
2. Cache architecture that separates data (entity objects), serializable
   representations (HtmlOption objects), and rendered output (HTML strings).
3. Cross-module access via FA hooks — any module can request DDL data without
   direct coupling to the owning module's repository or database tables.
4. Portable serialized cache blobs that can be stored in sessions, passed
   between modules, or persisted across requests.
5. Automatic cache invalidation when reference data is created, updated,
   or deleted.
6. Selection state (which option is pre-selected) treated as a rendering
   concern, not a data concern — the cache stores canonical option sets
   independent of any particular selection.

## Scope

This BR covers global reference data / lookup entities only. Record-specific
data (individual employees, customers, opportunities, payroll entries) is
explicitly out of scope.

## Applicable Entities by Module

### HRM (ksf_FA_HRM)
- Departments ( implemented — DepartmentService )
- Teams
- Role Dictionary (global role types)
- Grades
- Positions
- Employment Statuses
- Separation Reasons
- Pay Elements
- Benefits
- Pay Periods

### CRM (ksf_FA_CRM)
- Customer Types
- Territories
- Realms (opportunity categories)

### Project Management (ksf_FA_ProjectManagement)
- Project Types
- Priority levels (if promoted to DB lookup)
- Status values (if promoted to DB lookup)

## Related

- FR-006-001 (three-layer cache architecture)
- FR-006-002 (option cache key design)
- FR-006-003 (serialized cache portability)
- FR-006-004 (cache invalidation)
- FR-006-005 (hook contract — 3 hooks)
- FR-006-006 (blank option & mandatory validation)
