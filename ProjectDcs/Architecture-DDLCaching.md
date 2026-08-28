# Architecture — Cross-Module DDL Caching (Hybrid Approach)

**BR:** BR-006
**Modules:** ksf_FA_HRM, ksf_FA_CRM, ksf_FA_ProjectManagement
**FR:** FR-006-001 through FR-006-007

## Overview

Reference data (departments, grades, roles, territories, project types, etc.)
is owned by a single module and accessed by other modules through FA hooks.
The owning module caches the data in three layers and exposes it through a
standardized 3-hook contract.

## Hybrid Approach: Option B + Metadata-Ready

### Current State (Option B)

Each entity keeps its own:
- **Service class** with `DdlCacheTrait` for caching
- **Repository class** for DB queries
- **Entity class** for data representation
- **Admin page** for table view + add/edit form
- **3 hooks** in `hooks.php` for cross-module DDL access

Benefits: Explicit, debuggable, PHP 7.3 compatible, easy to customize per-entity.

### Future State (Option A — Metadata-Ready)

Each entity's Service implements `getFieldMetadata()` returning a structured
array describing fields, types, validation, FK DDLs, and display format.

A future `CrudUI` component can consume this metadata to render table views
and add/edit forms generically. **Not implemented now** — the metadata
exists as documentation and a structural contract.

See `FR-006-007_field-metadata-structure.md` for the full schema.

## Architecture Layers

```
┌─────────────────────────────────────────────────────────────────┐
│  Consumer Module (CRM, PM, etc.)                                │
│                                                                 │
│  hook_invoke('ksf_FA_HRM', 'getGradeDDL', $data)               │
│  hook_invoke('ksf_FA_HRM', 'getDepartmentDDL', $data)          │
│  hook_invoke('ksf_FA_HRM', 'getTeamDDL', $data)                │
│  → string[] of <option> HTML                                    │
└───────────────────────┬─────────────────────────────────────────┘
                        │ FA hook_invoke
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│  hooks.php (lazy autoload, delegates to service)                │
│                                                                 │
│  3 hooks per entity:                                            │
│  - get{Entity}        → entity arrays                           │
│  - get{Entity}DDL     → string[] of <option> HTML               │
│  - get{Entity}HtmlOpt → HtmlOption[] serializable objects       │
└───────────────────────┬─────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│  Service Layer (one per entity)                                 │
│                                                                 │
│  All services use DdlCacheTrait:                                │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │ DdlCacheTrait (ksfraser/html)                           │    │
│  │                                                         │    │
│  │ $optionCache: HtmlOption[][]  (serializable, portable)  │    │
│  │ $htmlCache:   string[][]      (pre-rendered HTML)       │    │
│  │                                                         │    │
│  │ +getOrBuildOptions(key, builder): HtmlOption[]           │    │
│  │ +getOrRenderHtml(key, options, selectedId): string[]     │    │
│  │ +getSerializedCache(): string                            │    │
│  │ +renderFromSerializedCache(ser, sel): string[]           │    │
│  │ +invalidateCache(): void                                 │    │
│  └─────────────────────────────────────────────────────────┘    │
│                                                                 │
│  Entity-specific logic stays in the Service:                    │
│  - Entity cache (Layer 1) if applicable                         │
│  - Entity-to-HtmlOption conversion                              │
│  - Business rules (create/update/delete validation)             │
│  - getFieldMetadata() for future generic UI                     │
└───────────────────────┬─────────────────────────────────────────┘
                        │ uses
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│  Repository Layer (one per entity)                              │
│                                                                 │
│  FA native db_query / db_fetch_assoc                            │
│  findActive(), findAll(), findById(), save(), update(), delete()│
└───────────────────────┬─────────────────────────────────────────┘
                        │ queries
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│  FA Database (0_hrm_*, 0_crm_*, etc.)                          │
└─────────────────────────────────────────────────────────────────┘
```

## DdlCacheTrait

Located in `Ksfraser\HTML\Traits\DdlCacheTrait` (`ksfraser/html`).

Any entity Service can `use DdlCacheTrait` to get:
- Two static cache layers (option + HTML)
- Serialization/portable blob utilities
- Cache invalidation
- State inspection for testing

The trait does NOT know about entities — it works with `HtmlOption[]`.
The Service handles entity-to-option conversion.

**Tests:** `ksfraser/html/tests/DdlCacheTraitTest.php` (21 tests, 49 assertions)

## Hook Contract (3 Hooks per Entity)

| Hook | Returns | Consumer use case |
|------|---------|-------------------|
| `get{Entity}` | Entity arrays | Business logic, iteration |
| `get{Entity}DDL` | `string[]` of `<option>` HTML | Echo into `<select>` |
| `get{Entity}HtmlOptions` | `HtmlOption[]` | Manipulate, serialize, cache |

### HRM Hook Map

| Entity | Hooks (in hooks.php) | Service Method |
|--------|---------------------|----------------|
| Department | `getDepartments` / `getDepartmentDDL` / `getDepartmentHtmlOptions` | `hookGetDepartments` / `hookGetDepartmentDDL` / `hookGetHtmlOptions` |
| Team | `getTeams` / `getTeamDDL` / `getTeamHtmlOptions` | `hookGetTeams` / `hookGetTeamDDL` / `hookGetTeamHtmlOptions` |
| Role | `getRoles` / `getRoleDDL` / `getRoleHtmlOptions` | `hookGetRoles` / `hookGetRoleDDL` / `hookGetRoleHtmlOptions` |
| RoleDictionary | `getRoleDictionary` / `getRoleDictionaryDDL` / `getRoleDictionaryHtmlOptions` | `hookGetRoleDictionary` / `hookGetRoleDictionaryDDL` / `hookGetRoleDictionaryHtmlOptions` |
| Grade | `getGrades` / `getGradeDDL` / `getGradeHtmlOptions` | `hookGetGrades` / `hookGetGradeDDL` / `hookGetGradeHtmlOptions` |
| Position | `getPositions` / `getPositionDDL` / `getPositionHtmlOptions` | `hookGetPositions` / `hookGetPositionDDL` / `hookGetPositionHtmlOptions` |
| EmploymentStatus | `getEmploymentStatuses` / `getEmploymentStatusDDL` / `getEmploymentStatusHtmlOptions` | `hookGetEmploymentStatuses` / `hookGetEmploymentStatusDDL` / `hookGetEmploymentStatusHtmlOptions` |
| Benefit | `getBenefits` / `getBenefitDDL` / `getBenefitHtmlOptions` | `hookGetBenefits` / `hookGetBenefitDDL` / `hookGetBenefitHtmlOptions` |

## Admin Page Pattern (Consistent Across All Entities)

Every entity admin page follows this structure:

```
1. Session + security setup
   include session.inc, add_access_extensions()

2. Service instantiation
   $service = new XxxService();

3. POST handlers (save/update/delete)
   if (isset($_POST['save_xxx']))   → $service->create($_POST)  → redirect
   if (isset($_POST['update_xxx'])) → $service->update($id, $_POST) → redirect

4. Form state
   $show_form = isset($_GET['add'])
   $edit_mode = isset($_GET['edit'])
   $edit_row  = $edit_mode ? $service->getById($id) : null

5. FK DDL via hooks (if entity has FK relationships)
   $fkOptions = hook_invoke('ksf_FA_HRM', 'getXxxDDL', $data);

6. Data for table
   $entities = $service->listAll();

7. HTML rendering
   - Card header with title + "Add New" button
   - Hidden form (display:none when not editing)
   - Table with entity data
   - JS: toggle form, data-required validation
```

### Page → Hook Dependency Matrix

| Page | Hooks Consumed |
|------|---------------|
| departments.php | (owner — uses OrgHierarchyService directly) |
| teams.php | `getDepartmentDDL` |
| roles.php | `getDepartmentDDL` |
| positions.php | `getDepartmentDDL` |
| grades.php | (no FK DDLs — standalone reference) |
| benefits.php | (no FK DDLs — standalone reference) |
| employees.php | `getDepartmentDDL` |

## Blank Option & Mandatory Validation

When `blankLabel` is provided, the blank option has `value=""`:

```html
<option value="">-- Select Department --</option>
```

Consumer pages validate via `data-required` + JS:

```javascript
document.querySelectorAll('select[data-required]').forEach(function(el) {
    el.form.addEventListener('submit', function(e) {
        if (el.value === '') { e.preventDefault(); el.focus(); }
    });
});
```

## Entity Inventory (HRM)

| Entity | Service | Repository | Entity | Admin Page | DdlCacheTrait | Hooks |
|--------|---------|------------|--------|------------|---------------|-------|
| Department | DepartmentService | DepartmentRepository | Department | departments.php | YES | YES |
| Team | TeamService | TeamRepository | Team | teams.php | **TODO** | **TODO** |
| Role | RoleService | RoleRepository | Role | roles.php | **TODO** | **TODO** |
| RoleDictionary | RoleDictionaryService | RoleRepository | RoleDictionary | (in roles.php) | **TODO** | **TODO** |
| Grade | GradeService | GradeRepository | Grade | grades.php | **TODO** | **TODO** |
| Position | PositionService | PositionRepository | Position | positions.php | **TODO** | **TODO** |
| EmploymentStatus | EmploymentStatusService | LookupRepository | EmploymentStatus | (in employees.php) | **TODO** | **TODO** |
| Benefit | BenefitsService | BenefitRepository | Benefit | benefits.php | **TODO** | **TODO** |

## Key Design Decisions

### selectedId is a Rendering Concern

The option cache key excludes `selectedId`. One cached option set serves
all consumers regardless of which option they select.

When `selectedId > 0`:
- `getHtmlOptions()` clones cached objects before applying `setSelected()`
- `getDdl()` renders directly from cache using value comparison

### Cache Objects Are Never Mutated

The option cache stores pristine `HtmlOption` objects. Selection is
applied via cloning (getHtmlOptions) or render-time comparison (getDdl).

### Static Cache, Request-Scoped

All cache layers are `static` properties — persist for one page load.
Reference data doesn't change mid-request. No shared cache backend needed.

### Each Entity Gets Its Own Cache

Each Service has its own static `$optionCache` and `$htmlCache` properties
from `DdlCacheTrait`. Caches are independent — invalidating Grade cache
does not affect Department cache.

## Message Flow — Cross-Module DDL Request

```
CRM Module              HRM hooks.php         DepartmentService    Repository    DB
   │                         │                      │                 │          │
   │ hook_invoke(            │                      │                 │
   │  'ksf_FA_HRM',         │                      │                 │
   │  'getDepartmentDDL',    │                      │                 │
   │  $data)                 │                      │                 │
   │────────────────────────>│                      │                 │
   │                         │ new DeptService()    │                 │
   │                         │─────────────────────>│                 │
   │                         │ hookGetDeptDDL()     │                 │
   │                         │─────────────────────>│                 │
   │                         │                      │ getDeptDDL()    │
   │                         │                      │────────┐        │
   │                         │                      │        │ cache  │
   │                         │                      │        │ hit?   │
   │                         │                      │◄───────┘        │
   │                         │                      │                 │
   │                         │                      │ [if miss:       │
   │                         │                      │  getDepartments]│
   │                         │                      │────────────────>│
   │                         │                      │                 │SELECT
   │                         │                      │                 │──────>│
   │                         │                      │                 │<──────│
   │                         │                      │◄────────────────│
   │                         │                      │                 │
   │                         │                      │ build Options   │
   │                         │                      │ store cache     │
   │                         │                      │                 │
   │                         │ string[]             │                 │
   │                         │◄─────────────────────│                 │
   │ string[]                │                      │                 │
   │◄────────────────────────│                      │                 │
   │                         │                      │                 │
   │ echo into <select>      │                      │                 │
```

## Message Flow — Page Admin DDL (Same Module)

```
teams.php              TeamService (DdlCacheTrait)    DepartmentService
   │                          │                            │
   │ hook_invoke(             │                            │
   │  'ksf_FA_HRM',          │                            │
   │  'getDepartmentDDL',     │                            │
   │  $data)                  │                            │
   │─────────────────────────>│                            │
   │                          │ hookGetDepartmentDDL()     │
   │                          │───────────────────────────>│
   │                          │                            │ getDeptDDL()
   │                          │                            │──── cache ──│
   │                          │ string[]                   │             │
   │                          │◄───────────────────────────│             │
   │ string[]                 │                            │
   │◄─────────────────────────│                            │
   │                          │                            │
   │ foreach as $optHtml      │                            │
   │   echo $optHtml          │                            │
   │   (with selected state)  │                            │
```

## Process Flow — Admin Page Lifecycle

```
User clicks "Add Team"
        │
        ▼
teams.php?view=teams&add=1
        │
        ▼
┌─────────────────────────────────┐
│ 1. Session + security setup     │
│ 2. Service instantiation        │
│ 3. $show_form = true            │
│ 4. hook_invoke('getDeptDDL')    │──► DepartmentService cache
│ 5. $service->getParentTeams()   │──► TeamRepository.findByDept()
│ 6. $service->listAll()          │──► TeamRepository.findAll()
└─────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────┐
│ Render:                         │
│  - Card header + "Add" button   │
│  - Form with department DDL     │
│    (hook result echoed)         │
│  - Teams table                  │
│  - JS: toggle + validation      │
└─────────────────────────────────┘
        │
        ▼
User fills form, clicks "Save"
        │
        ▼
POST teams.php
        │
        ▼
┌─────────────────────────────────┐
│ 1. $service->create($_POST)     │──► TeamRepository.save()
│ 2. TeamService::invalidateCache()   (if DdlCacheTrait added)
│ 3. header('Location: ...')      │
│ 4. exit                         │
└─────────────────────────────────┘
        │
        ▼
Redirect to teams.php?view=teams
        │
        ▼
Table shows new team (cache was invalidated, fresh data loaded)
```
