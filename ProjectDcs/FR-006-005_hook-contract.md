# FR-006-005 — Hook Contract for DDL Access

**Parent:** BR-006 (Cross-Module DDL Caching)
**Status:** IMPLEMENTED (DepartmentService hooks)

## Functional Requirement

Each module that owns cacheable reference data SHALL expose three hooks
for DDL access. These hooks are the ONLY interface through which other
modules access reference data — no module touches another module's
database tables directly.

### Hook Definitions (3 hooks)

| Hook Name | Returns | Purpose |
|-----------|---------|---------|
| `get{Entity}` | Entity arrays | Raw entity data for business logic |
| `get{Entity}DDL` | `string[]` | Pre-rendered `<option>` HTML strings |
| `get{Entity}HtmlOptions` | `HtmlOption[]` | Serializable objects for manipulation |

### Example: Department Hooks (ksf_FA_HRM)

```
hook_invoke('ksf_FA_HRM', 'getDepartments', $data)
hook_invoke('ksf_FA_HRM', 'getDepartmentDDL', $data)
hook_invoke('ksf_FA_HRM', 'getDepartmentHtmlOptions', $data)
```

### Portable Serialized Representations

Consumers who need portable serialized blobs can serialize the
`HtmlOption[]` array from `getDepartmentHtmlOptions` directly:

```php
$data = ['active_only' => true, 'blank_label' => '-- Select --'];
$options = hook_invoke('ksf_FA_HRM', 'getDepartmentHtmlOptions', $data);
$serialized = serialize($options);

// Store in session, pass to another module, etc.
$_SESSION['dept_cache'] = $serialized;

// Later: unserialize and use
$cache = unserialize($_SESSION['dept_cache']);
foreach ($cache as $option) {
    echo $option->getHtml();
}
```

The service also provides `getSerializedCache()` and
`renderFromSerializedCache()` as convenience utilities (not hooks).

### Hook Payload Standards

All hooks accept `$data` (by reference) and `$opts` (optional).

Common `$data` keys:

| Key | Type | Default | Used By |
|-----|------|---------|---------|
| `active_only` | `bool` | `true` | All hooks |
| `blank_label` | `string` | `''` | DDL, HtmlOptions |
| `format` | `string` | `'{code} - {name}'` | DDL, HtmlOptions |
| `selected_id` | `int` | `0` | DDL, HtmlOptions |

### Implementation in hooks.php

Each hook method in the module's `hooks.php`:

1. Loads the autoloader lazily (guarded by `file_exists()`).
2. Instantiates the service class.
3. Delegates to the corresponding `hook*` method on the service.

```php
function getDepartmentDDL(&$data, $opts = null)
{
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) { return []; }
    require_once $autoload;

    $service = new DepartmentService();
    return $service->hookGetDepartmentDDL($data, $opts);
}
```

## Related

- BR-006 (Cross-Module DDL Caching)
- FR-006-001 (three-layer cache architecture)
- FR-006-003 (serialized cache portability — utility methods)
