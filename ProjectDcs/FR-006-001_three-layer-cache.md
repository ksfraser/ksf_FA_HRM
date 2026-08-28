# FR-006-001 — Three-Layer Cache Architecture

**Parent:** BR-006 (Cross-Module DDL Caching)
**Status:** IMPLEMENTED (DepartmentService)

## Functional Requirement

The system SHALL provide a three-layer cache for each cacheable reference
data entity, with each layer serving a distinct purpose:

### Layer 1: Entity Cache (`$entityCache`)

- Stores `Department[]` (or equivalent entity array) keyed by
  `'active' | 'all'`.
- Source of truth for entity data within the request.
- Populated on first access via repository query.
- Invalidated on any write operation (create/update/delete).

### Layer 2: Option Cache (`$optionCache`)

- Stores `HtmlOption[][]` keyed by `'active_only|blank_label|format'`.
- This is the PRIMARY cache — serializable, portable, walkable.
- Consumers can unserialize, iterate, and cherry-pick options.
- **MUST NOT** include `selectedId` in the cache key — selection is a
  rendering concern applied at Layer 3.
- Cached objects MUST remain pristine — never mutated after creation.
- When selection is needed, objects MUST be cloned before applying
  `setSelected()`.

### Layer 3: HTML Cache (`$htmlCache`)

- Stores `string[][]` (pre-rendered `<option>` HTML) keyed by
  `'active_only|blank_label|format|selectedId'`.
- Derived from the option cache by rendering each `HtmlOption::getHtml()`.
- The `selectedId` IS in this key because rendered output differs per
  selection state.
- Rendered directly from cached objects — no cloning needed (read-only
  render with value comparison).

## Implementation Notes

`DepartmentService` implements this pattern. Each cacheable entity service
in HRM, CRM, and PM SHALL follow the same three-layer structure.

The `getHtmlOptions()` method returns cloned objects when `selectedId > 0`
to preserve the pristine cache. The `getDepartmentDDL()` method renders
directly from cache using value comparison for the selected state.

## Related

- BR-006 (Cross-Module DDL Caching)
- FR-006-002 (option cache key design)
- FR-006-004 (cache invalidation)
