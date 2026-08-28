# FR-006-007 — Field Metadata Structure (Hybrid Approach)

**Parent:** BR-006 (Cross-Module DDL Caching)
**Status:** DESIGN PHASE

## Purpose

Define a field metadata schema that:
1. Documents each entity's DDL contract today (Option B: consistent pattern)
2. Is structured so a future generic CRUD UI (Option A) can consume it directly

This is **not a code change** — it's a documentation/structure contract that each
entity's Service can optionally implement.

## Metadata Schema

Each entity defines a `getFieldMetadata()` method returning:

```php
/**
 * Return field metadata for this entity.
 *
 * Used today for documentation and DDL configuration.
 * Future: drives generic CRUD UI rendering.
 *
 * @return array Entity metadata
 */
public static function getFieldMetadata(): array
{
    return [
        // ─── Entity Identity ──────────────────────────────
        'entity'    => 'grade',                    // machine name (snake_case)
        'table'     => '0_hrm_grades',            // FA table (literal 0_ prefix)
        'label'     => 'Grade',                    // human-readable singular
        'labelPlural' => 'Grades',                 // human-readable plural
        'hookPrefix' => 'Grade',                   // hook name component: get{HookPrefix}

        // ─── Primary Key ──────────────────────────────────
        'pk'        => 'grade_id',                 // primary key column

        // ─── Fields ───────────────────────────────────────
        'fields'    => [
            'grade_code' => [
                'label'    => 'Code',
                'type'     => 'text',              // text|number|checkbox|select|textarea|date
                'required' => true,
                'max'      => 20,                  // max length (text)
                'showInTable' => true,             // display in list view
                'showInForm'  => true,             // display in add/edit form
                'colClass' => 'col-md-2',          // Bootstrap grid class for form
            ],
            'grade_name' => [
                'label'    => 'Name',
                'type'     => 'text',
                'required' => true,
                'max'      => 100,
                'showInTable' => true,
                'showInForm'  => true,
                'colClass' => 'col-md-3',
            ],
            'min_salary' => [
                'label'    => 'Min Salary',
                'type'     => 'number',
                'required' => false,
                'step'     => '0.01',              // input step (number)
                'min'      => 0,                   // minimum value
                'format'   => 'price',             // display format: price|number|date|text
                'showInTable' => true,
                'showInForm'  => true,
                'colClass' => 'col-md-2',
            ],
            'max_salary' => [
                'label'    => 'Max Salary',
                'type'     => 'number',
                'required' => false,
                'step'     => '0.01',
                'min'      => 0,
                'format'   => 'price',
                'showInTable' => true,
                'showInForm'  => true,
                'colClass' => 'col-md-2',
            ],
            'description' => [
                'label'    => 'Description',
                'type'     => 'textarea',
                'required' => false,
                'rows'     => 2,
                'showInTable' => true,
                'showInForm'  => true,
                'colClass' => 'col-md-8',
            ],
            'is_active' => [
                'label'    => 'Active',
                'type'     => 'checkbox',
                'required' => false,
                'default'  => 1,                   // default value for new records
                'showInTable' => true,             // renders as badge
                'showInForm'  => true,
                'colClass' => 'col-md-2',
            ],
        ],

        // ─── FK DDLs (consumed by this entity) ────────────
        // Key = form field name, Value = hook config
        'fk_ddls'   => [],  // Grade has no FK DDLs

        // ─── DDL Hooks (provided BY this entity) ──────────
        // For other modules to consume via hook_invoke
        'ddlHooks'  => [
            'getGrades'              => 'hookGetGrades',
            'getGradeDDL'            => 'hookGetGradeDDL',
            'getGradeHtmlOptions'    => 'hookGetGradeHtmlOptions',
        ],

        // ─── Display Settings ─────────────────────────────
        // NOTE: `table` is reserved for the DB table name (see identity
        // above). Display/sorting settings live under `tableSettings` to
        // avoid a key collision. This resolves the earlier ambiguity where
        // both used `table`.
        'tableSettings' => [
            'orderBy' => 'grade_code ASC',         // default sort
        ],
    ];
}
```

## Field Types

| Type | HTML Output | Notes |
|------|-------------|-------|
| `text` | `<input type="text">` | Uses `max` for maxlength |
| `number` | `<input type="number">` | Uses `step`, `min`, `max` |
| `checkbox` | `<input type="checkbox">` | Value=1 when checked |
| `select` | `<select>` | Options from `options` array or FK DDL |
| `textarea` | `<textarea>` | Uses `rows` for height |
| `date` | `<input type="date">` | ISO date format |

## Display Formats

| Format | Rendering | Example |
|--------|-----------|---------|
| `price` | `price_format($val)` | `$1,234.56` |
| `number` | `number_format($val)` | `1,234` |
| `date` | `date_format($val)` | `2026-01-15` |
| `text` | `htmlspecialchars($val)` | Raw text |

## FK DDL Configuration

When an entity has foreign key dropdowns (e.g. `department_id` on Teams):

```php
'fk_ddls' => [
    'department_id' => [
        'hookModule'  => 'ksf_FA_HRM',          // which module provides the DDL
        'hookName'    => 'getDepartmentDDL',     // hook name
        'blankLabel'  => '-- Select Department --',
        'required'    => true,
        'dataRequired' => true,                  // JS validation via data-required
    ],
],
```

## Entity-to-Hook Mapping (HRM)

| Entity | Table | Hook Prefix | FK DDLs | Has Admin Page |
|--------|-------|-------------|---------|----------------|
| Department | `0_hrm_departments` | Department | parent_id (self) | YES |
| Team | `0_hrm_teams` | Team | department_id, parent_team_id | YES |
| Role | `0_hrm_roles` | Role | department_id, role_dict_id | YES |
| RoleDictionary | `0_hrm_role_dictionary` | RoleDictionary | (none) | NO (embedded in roles) |
| Grade | `0_hrm_grades` | Grade | (none) | YES |
| Position | `0_hrm_positions` | Position | department_id, team_id, role_id | YES |
| EmploymentStatus | `0_hrm_employment_status` | EmploymentStatus | (none) | NO (embedded in employees) |
| Benefit | `0_hrm_benefits` | Benefit | (none) | YES |

## Future Generic CRUD UI (Option A)

When all entities provide `getFieldMetadata()`, a generic CRUD component can:

```php
// Future: generic admin page
$meta = TeamService::getFieldMetadata();
$page = new CrudUI($meta, $service);
$page->render();  // table + add/edit form + validation
```

The metadata drives:
- Table column headers and cell rendering
- Form field layout and validation
- FK DDL hook calls
- Sort order
- Badge rendering for `is_active`

**This is NOT implemented now** — the metadata exists as documentation
and a structural contract. Each entity still has its own hand-crafted
admin page following the consistent pattern.

## Acceptance Criteria

1. Each HRM entity Service implements `getFieldMetadata()` returning the schema
2. Metadata matches the actual DB schema and existing page behavior
3. FK DDL configs match the hooks defined in `hooks.php`
4. The metadata is documented in each entity's PHPDoc
5. A future generic CRUD UI can consume this metadata without changes
