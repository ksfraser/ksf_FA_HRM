# FR-006-002 — Option Cache Key Design

**Parent:** BR-006 (Cross-Module DDL Caching)
**Status:** IMPLEMENTED (DepartmentService)

## Functional Requirement

The option cache (Layer 2) MUST be keyed by data parameters only — never
by rendering parameters:

```
Cache key: '{active_only}|{blank_label}|{format}'
```

The `selectedId` parameter MUST NOT appear in the option cache key because:

1. The same canonical option set serves multiple consumers with different
   selections within a single request (e.g., employees page showing 50
   employees each with a department dropdown pre-selected to different
   values).
2. The option cache stores pure data objects — selection is metadata applied
   at render time.
3. Including `selectedId` in the key would fragment the cache unnecessarily,
   defeating the purpose of sharing one option set across consumers.

## Service Method Signature

```php
public function getHtmlOptions(
    bool $activeOnly = true,
    string $blankLabel = '',
    string $formatString = '{code} - {name}',
    int $selectedId = 0    // NOT in cache key
): array
```

The `$selectedId` parameter is used to clone and mark the appropriate option,
but the cache lookup/store uses only `$activeOnly`, `$blankLabel`, and
`$formatString`.

## Cache Key Examples

| activeOnly | blankLabel | format | Cache Key |
|-----------|-----------|--------|-----------|
| true | '' | '{code} - {name}' | `active||{code} - {name}` |
| true | '-- None --' | '{name}' | `active|-- None --|{name}` |
| false | '' | '{id}' | `all||{id}` |

## Related

- BR-006 (Cross-Module DDL Caching)
- FR-006-001 (three-layer cache architecture)
