# FR-006-006 — Blank Option & Mandatory Field Validation

**Parent:** BR-006 (Cross-Module DDL Caching)
**Status:** IMPLEMENTED (DepartmentService)

## Functional Requirement

When a DDL includes a blank option (e.g., "-- Select Department --"), the
blank option MUST have `value=""` to serve as a sentinel for "no selection".

### Blank Option Behavior

The `blankLabel` parameter controls blank option generation:

| blankLabel | Effect |
|-----------|--------|
| `''` (default) | No blank option added |
| `'<string>'` | Blank option with `value=""` prepended to option list |

The blank option is the FIRST entry in the list (index 0) so it appears
as the default when no selection is made.

### Mandatory Field Validation

Consumer pages SHOULD implement client-side validation to prevent form
submission when a mandatory DDL still has the blank value selected.

**Approach 1: data-required attribute (recommended)**

The consumer adds `data-required="1"` to the `<select>` element and
includes a standard JS validator:

```html
<select name="department_id" data-required="1">
    <option value="">-- Select Department --</option>
    <option value="1">IT</option>
    ...
</select>
```

```javascript
document.querySelectorAll('select[data-required]').forEach(function(el) {
    el.addEventListener('change', function() {
        this.style.borderColor = this.value !== '' ? '' : 'red';
    });
    this.form.addEventListener('submit', function(e) {
        if (el.value === '') {
            e.preventDefault();
            el.focus();
            el.style.borderColor = 'red';
        }
    });
});
```

**Approach 2: HTML5 required attribute**

```html
<select name="department_id" required>
    <option value="">-- Select Department --</option>
    ...
</select>
```

Browser-native validation prevents submission. No JS needed. However,
the error message is browser-default and less customizable.

### Consumer Contract

- If `blankLabel` is provided, the first option has `value=""`.
- If the field is mandatory, the consumer MUST add validation (either
  `data-required` + JS, or HTML5 `required`).
- The service does NOT enforce mandatory validation — it provides the
  blank sentinel; the consumer enforces the constraint.

## Implementation Notes

`DepartmentService::getHtmlOptions()` creates `new HtmlOption('', $blankLabel)`
when `$blankLabel !== ''`. The empty string value is the sentinel.

`DepartmentService::getDepartmentDDL()` renders this as
`<option value="">-- Select Department --</option>`.

## Related

- BR-006 (Cross-Module DDL Caching)
- FR-006-001 (three-layer cache architecture)
- FR-006-002 (option cache key design — blankLabel is in the key)
