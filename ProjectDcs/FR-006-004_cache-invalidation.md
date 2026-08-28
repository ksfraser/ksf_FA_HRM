# FR-006-004 — Cache Invalidation

**Parent:** BR-006 (Cross-Module DDL Caching)
**Status:** IMPLEMENTED (DepartmentService)

## Functional Requirement

All three cache layers MUST be invalidated when reference data changes.
Invalidation MUST occur automatically on any write operation performed
through the owning service.

### Invalidation Triggers

| Operation | Method | Effect |
|-----------|--------|--------|
| Create entity | `Service::create()` | `invalidateCache()` called after DB insert |
| Update entity | `Service::update()` | `invalidateCache()` called after DB update |
| Delete entity | `Service::delete()` | `invalidateCache()` called after DB delete |

### Invalidation Scope

`invalidateCache()` is a static method that resets ALL three layers:

```php
public static function invalidateCache(): void
{
    self::$entityCache = null;
    self::$optionCache = null;
    self::$htmlCache = null;
}
```

All layers are reset (not selective) because:
1. Entity changes affect the entity cache.
2. Entity changes affect which options appear in the option cache.
3. Entity changes affect rendered HTML output.
4. Selective invalidation adds complexity with negligible performance
   benefit — reference data changes are infrequent.

### External Invalidation

`invalidateCache()` is public and static, allowing external code to
invalidate caches when data changes outside the service (e.g., direct
SQL updates, FA installation hooks).

## Implementation Notes

`DepartmentService::create()`, `update()`, and `delete()` all call
`self::invalidateCache()` after the repository operation completes.

## Related

- BR-006 (Cross-Module DDL Caching)
- FR-006-001 (three-layer cache architecture)
