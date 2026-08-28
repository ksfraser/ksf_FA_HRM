# UML — Cross-Module DDL Caching (Hybrid Approach)

**BR:** BR-006
**Modules:** ksf_FA_HRM, ksf_FA_CRM, ksf_FA_ProjectManagement
**FR:** FR-006-001 through FR-006-007

## Class Diagram — All HRM Entity Services

```
┌──────────────────────────────────────────────────────────────────────────┐
│                     DdlCacheTrait (ksfraser/html)                        │
│                                                                          │
│  Static caches:                                                          │
│  -optionCache: HtmlOption[][]|null  (serializable, portable)             │
│  -htmlCache:   string[][]|null      (pre-rendered HTML)                  │
│                                                                          │
│  +getOrBuildOptions(key, builder): HtmlOption[]                          │
│  +getOrRenderHtml(key, options, selectedId): string[]                    │
│  +getSerializedCache(): string                                           │
│  +renderFromSerializedCache(ser, sel): string[]                          │
│  +invalidateCache(): void                                                │
│  +getOptionCacheState(): ?array                                          │
│  +getHtmlCacheState(): ?array                                            │
└──────────┬───────────────────────────────────────────────────┬───────────┘
           │ use                                               │ use
    ┌──────┴───────┐ ┌──────────────┐ ┌──────────────┐  ┌────┴──────────┐
    │Department    │ │ TeamService  │ │ RoleService  │  │ GradeService  │
    │Service       │ │              │ │              │  │               │
    ├──────────────┤ ├──────────────┤ ├──────────────┤  ├───────────────┤
    │-repo         │ │-teamRepo     │ │-roleRepo     │  │-gradeRepo     │
    │-entityCache  │ │              │ │-deptRepo     │  │               │
    │              │ │              │ │              │  │               │
    │+getDepts()   │ │+listAll()    │ │+listAll()    │  │+listAll()     │
    │+getHtmlOpts()│ │+getById()    │ │+getById()    │  │+getById()     │
    │+getDeptDDL() │ │+create()     │ │+create()     │  │+create()      │
    │+create()     │ │+update()     │ │+update()     │  │+update()      │
    │+update()     │ │+delete()     │ │+delete()     │  │+delete()      │
    │+delete()     │ │+getParentT() │ │+getFormDD()  │  │               │
    │              │ │+getTeamsFD() │ │+getRolesFD() │  │               │
    ├──────────────┤ ├──────────────┤ ├──────────────┤  ├───────────────┤
    │hookGetDepts()│ │hookGetTeams()│ │hookGetRoles()│  │hookGetGrades()│
    │hookGetDDl()  │ │hookGetTDDL() │ │hookGetRDDL() │  │hookGetGDDL()  │
    │hookGetHtmlO()│ │hookGetHtmlO()│ │hookGetHtmlO()│  │hookGetHtmlO() │
    └──────┬───────┘ └──────┬───────┘ └──────┬───────┘  └───────┬───────┘
           │                │                │                   │
    ┌──────┴───────┐ ┌──────┴───────┐ ┌──────┴───────┐  ┌───────┴───────┐
    │DeptRepo      │ │ TeamRepo     │ │ RoleRepo     │  │ GradeRepo     │
    │findActive()  │ │findActive()  │ │findAll()     │  │findAll()      │
    │findAll()     │ │findAll()     │ │findActive()  │  │findActive()   │
    │findById()    │ │findById()    │ │findById()    │  │findById()     │
    │save()        │ │save()        │ │save()        │  │save()         │
    │update()      │ │update()      │ │update()      │  │update()       │
    │delete()      │ │delete()      │ │delete()      │  │               │
    └──────┬───────┘ └──────┬───────┘ └──────┬───────┘  └───────┬───────┘
           │                │                │                   │
    ┌──────┴───────┐ ┌──────┴───────┐ ┌──────┴───────┐  ┌───────┴───────┐
    │Department    │ │ Team         │ │ Role         │  │ Grade         │
    │Entity        │ │ Entity       │ │ Entity       │  │ Entity        │
    │+toArray()    │ │+toArray()    │ │+toArray()    │  │+toArray()     │
    └──────────────┘ └──────────────┘ └──────────────┘  └───────────────┘

┌──────────────────────────────────────────────────────────────────────────┐
│ Additional entities (same pattern):                                      │
│                                                                          │
│ PositionService ──┬── PositionRepository ──┬── Position                  │
│  uses DdlCacheTrait│  dept_id FK (hook)     │                            │
│                    │  team_id FK (hook)     │                            │
│                    │  role_id FK (hook)     │                            │
│                                                                          │
│ RoleDictionaryService ──┬── RoleRepository ──┬── RoleDictionary          │
│  uses DdlCacheTrait     │  (shared w/ Roles) │                          │
│                         │  no FK DDLs        │                           │
│                                                                          │
│ EmploymentStatusService ──┬── LookupRepository ──┬── EmploymentStatus    │
│  uses DdlCacheTrait       │  (shared w/ Lookups) │                      │
│                           │  no FK DDLs          │                       │
│                                                                          │
│ BenefitsService ──┬── BenefitRepository ──┬── Benefit                    │
│  uses DdlCacheTrait│  no FK DDLs           │                             │
│                   │                       │                              │
└──────────────────────────────────────────────────────────────────────────┘
```

## Class Diagram — hooks.php Dispatch

```
┌──────────────────────────────────────────────────────────────────────────┐
│                     hooks_ksf_FA_HRM (hooks.php)                         │
│                                                                          │
│  Department hooks:                                                       │
│  +getDepartments(&$data, $opts): array                                   │
│  +getDepartmentDDL(&$data, $opts): array                                 │
│  +getDepartmentHtmlOptions(&$data, $opts): HtmlOption[]                  │
│                                                                          │
│  Team hooks:                                                             │
│  +getTeams(&$data, $opts): array                                         │
│  +getTeamDDL(&$data, $opts): array                                       │
│  +getTeamHtmlOptions(&$data, $opts): HtmlOption[]                        │
│                                                                          │
│  Role hooks:                                                             │
│  +getRoles(&$data, $opts): array                                         │
│  +getRoleDDL(&$data, $opts): array                                       │
│  +getRoleHtmlOptions(&$data, $opts): HtmlOption[]                        │
│                                                                          │
│  RoleDictionary hooks:                                                   │
│  +getRoleDictionary(&$data, $opts): array                                │
│  +getRoleDictionaryDDL(&$data, $opts): array                             │
│  +getRoleDictionaryHtmlOptions(&$data, $opts): HtmlOption[]              │
│                                                                          │
│  Grade hooks:                                                            │
│  +getGrades(&$data, $opts): array                                        │
│  +getGradeDDL(&$data, $opts): array                                      │
│  +getGradeHtmlOptions(&$data, $opts): HtmlOption[]                       │
│                                                                          │
│  Position hooks:                                                         │
│  +getPositions(&$data, $opts): array                                     │
│  +getPositionDDL(&$data, $opts): array                                   │
│  +getPositionHtmlOptions(&$data, $opts): HtmlOption[]                    │
│                                                                          │
│  EmploymentStatus hooks:                                                 │
│  +getEmploymentStatuses(&$data, $opts): array                            │
│  +getEmploymentStatusDDL(&$data, $opts): array                           │
│  +getEmploymentStatusHtmlOptions(&$data, $opts): HtmlOption[]            │
│                                                                          │
│  Benefit hooks:                                                          │
│  +getBenefits(&$data, $opts): array                                      │
│  +getBenefitDDL(&$data, $opts): array                                    │
│  +getBenefitHtmlOptions(&$data, $opts): HtmlOption[]                     │
│                                                                          │
│  Department admin hooks:                                                 │
│  +teams(&$data, $opts): array                                            │
│  +roles(&$data, $opts): array                                            │
└──────────────────────────────────────────────────────────────────────────┘
```

## Sequence Diagram — Any Entity DDL Request

```
Consumer          hooks.php       XxxService          Repository    DB
   │                  │                │                   │          │
   │ hook_invoke(     │                │                   │
   │  'getXxxDDL')    │                │                   │
   │─────────────────>│                │                   │
   │                  │ new XxxService()                   │
   │                  │───────────────>│                   │
   │                  │ hookGetXxxDDL()│                   │
   │                  │───────────────>│                   │
   │                  │                │ getDdl()          │
   │                  │                │────────┐          │
   │                  │                │        │ cache    │
   │                  │                │        │ hit?     │
   │                  │                │◄───────┘          │
   │                  │                │                   │
   │                  │                │ [if miss:         │
   │                  │                │  getEntities()]   │
   │                  │                │──────────────────>│
   │                  │                │                   │SELECT
   │                  │                │                   │──────>│
   │                  │                │                   │<──────│
   │                  │                │◄──────────────────│
   │                  │                │                   │
   │                  │                │ build HtmlOption[]│
   │                  │                │ store in cache    │
   │                  │                │                   │
   │                  │                │ render to htmlCache
   │                  │ string[]       │                   │
   │                  │◄───────────────│                   │
   │ string[]         │                │                   │
   │◄─────────────────│                │                   │
```

## Sequence Diagram — Admin Page with FK DDL

```
teams.php          hooks.php       TeamService     DeptService    TeamRepo    DB
   │                   │               │               │             │         │
   │ hook_invoke(      │               │               │             │
   │  'getDeptDDL')    │               │               │             │
   │──────────────────>│               │               │             │
   │                   │ hookGetDDL()  │               │             │
   │                   │──────────────>│               │             │
   │                   │               │ hookInvoke()  │             │
   │                   │               │──────────────>│             │
   │                   │               │               │ getDeptDDL()│
   │                   │               │               │─────cache──>│
   │                   │               │               │             │
   │                   │ string[]      │               │             │
   │                   │◄──────────────│               │             │
   │ string[]          │               │               │             │
   │◄──────────────────│               │               │             │
   │                   │               │               │             │
   │ $service->getParentTeams($deptId) │               │             │
   │──────────────────────────────────>│               │             │
   │                   │               │ findByDept()  │             │
   │                   │               │─────────────────────────────>│
   │                   │               │               │             │
   │ $service->listAll()               │               │             │
   │──────────────────────────────────>│               │             │
   │                   │               │ findAll()     │             │
   │                   │               │─────────────────────────────>│
   │                   │               │               │             │
   │ Entity[]          │               │               │             │
   │◄──────────────────────────────────│               │             │
   │                   │               │               │             │
   │ Render form + table + DDL         │               │             │
```

## Message Flow — Cache Invalidation

```
teams.php (POST save)     TeamService          DeptService
   │                         │                     │
   │ $service->create($data) │                     │
   │────────────────────────>│                     │
   │                         │ save() → DB         │
   │                         │──────┐              │
   │                         │      │ repo->save() │
   │                         │◄─────┘              │
   │                         │                     │
   │ TeamService::            │                     │
   │  invalidateCache()      │                     │
   │────────────────────────>│                     │
   │                         │ $optionCache = null │
   │                         │ $htmlCache = null   │
   │                         │                     │
   │ header('Location:')     │                     │
   │                         │                     │
   ... next request ...
   │                         │                     │
   │ hook_invoke(            │                     │
   │  'getTeamDDL')          │                     │
   │────────────────────────>│                     │
   │                         │ cache miss → rebuild │
```

## Blank Option & Mandatory Validation Pattern

```
┌──────────────────────────────────────────────────────────────┐
│  <select name="department_id" data-required="1">             │
│    <option value="">-- Select Department --</option>         │  ← blankLabel
│    <option value="1">IT - Info Tech</option>                 │
│    <option value="2">HR - Human Resources</option>           │
│  </select>                                                   │
└──────────────────────────────────────────────────────────────┘
                        │
                        ▼ JS validator
┌──────────────────────────────────────────────────────────────┐
│  select[data-required] → on submit:                           │
│    if (value === '') → preventDefault, focus, red             │
└──────────────────────────────────────────────────────────────┘
```

## Process Flow — Adding a New Entity

To add DDL caching for a new entity (e.g. "Grade"):

```
1. Entity class
   └── src/Entity/Grade.php (already exists)

2. Repository class
   └── src/Repository/GradeRepository.php (already exists)
       └── findActive(), findAll(), findById(), save(), update()

3. Service class
   └── src/Service/GradeService.php
       ├── use DdlCacheTrait;
       ├── getEntities($activeOnly): Grade[]     ← entity cache
       ├── getHtmlOptions($activeOnly, $blank, $fmt, $selected): HtmlOption[]
       ├── getDdl($activeOnly, $blank, $fmt, $selected): string[]
       ├── getGradeSelect(...): HtmlSelect        ← convenience
       ├── create($data): int                     ← invalidates cache
       ├── update($id, $data): void               ← invalidates cache
       ├── delete($id): void                      ← invalidates cache
       ├── hookGetGrades(&$data, $opts): array
       ├── hookGetGradeDDL(&$data, $opts): array
       └── hookGetGradeHtmlOptions(&$data, $opts): HtmlOption[]

4. hooks.php
   ├── const SS_xxx_GRADES = NNN << 8;
   ├── install_access() — SA_HRM_GRADES
   └── 3 hook methods:
       ├── getGrades(&$data, $opts) → delegate to service
       ├── getGradeDDL(&$data, $opts) → delegate to service
       └── getGradeHtmlOptions(&$data, $opts) → delegate to service

5. Admin page
   └── pages/grades.php
       ├── hook_invoke for FK DDLs (if any)
       ├── $service->listAll() for table
       ├── form with entity fields
       └── JS: toggle form, data-required validation

6. Tests
   └── tests/Unit/GradeServiceTest.php
       ├── test cache layers
       ├── test hook responses
       ├── test cache invalidation
       └── test serialized cache
```
