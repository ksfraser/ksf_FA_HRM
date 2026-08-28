# FR-006-003 — Serialized Cache Portability

**Parent:** BR-006 (Cross-Module DDL Caching)
**Status:** IMPLEMENTED (DepartmentService)

## Functional Requirement

The system SHALL provide a serialized representation of the option cache
(Layer 2) that can be:

1. Stored in PHP sessions for cross-request persistence.
2. Passed between modules via hook responses.
3. Unserialized by any consumer with access to the `HtmlOption` class.
4. Rendered to HTML strings with an arbitrary selection state.

## Hook: `getSerializedDepartmentCache`

Returns:

```php
[
    'serialized' => serialize(self::$optionCache),  // string
    'cache_keys' => ['active||{code} - {name}', ...],  // string[]
]
```

The consumer stores the `serialized` string and later passes it to
`renderFromSerializedDepartmentCache`.

## Hook: `renderFromSerializedDepartmentCache`

Takes:

```php
$data = [
    'serialized' => '...',  // from getSerializedDepartmentCache
    'selected_id' => 0,     // optional
]
```

Returns: `string[]` — pre-rendered `<option>` HTML strings.

## Consumer Flow

```
Module A: hook_invoke('ksf_FA_HRM', 'getSerializedDepartmentCache', $data)
  → stores $result['serialized'] in session

Module B: (later, different request)
  $data['serialized'] = $_SESSION['dept_cache'];
  $data['selected_id'] = $employee->departmentId;
  hook_invoke('ksf_FA_HRM', 'renderFromSerializedDepartmentCache', $data)
  → returns pre-rendered <option> strings with correct selection
```

## Implementation Notes

- `unserialize()` errors are suppressed with `@` — invalid input returns
  an empty array.
- The serialized blob contains `HtmlOption` objects — consumers need the
  `ksfraser/html` package to deserialize.

## Related

- BR-006 (Cross-Module DDL Caching)
- FR-006-001 (three-layer cache architecture)
