# FR-HRM-010-001 — HRM app-shell front controller

@BABOK Related: BR-HRM-010; FR-006-007 (field metadata consumed by the tab
          controllers); AGENTS_ARCH.md §9 (FA page security).
@UML : APP_TAB_ARCHITECTURE.md §11 (host page = thin router);
        `ksfraser\FrontAccounting\Common\App\AbstractFrontController`.
Status: Approved — BABOK; **implemented**, commit `18fcba5` (plus `ecbeff6`,
       `5906c1c`). Package `ksfraser/ksf-fa-common` v1.0.13 / v1.0.14.
Module: ksf_FA_HRM (dev tree `~/Documents/ksf_FA_HRM`, deployed
       `~/Documents/ksf_Infrastructure/fa_modules/ksf_FA_HRM`).

> **Traceability caveat.** The code carries `@BABOK Related: FR-HRM-001 (App
> shell tab registration)` in `HrmAppShell.php` and in the shipped
> `AbstractAppShell`/`TabRegistration` docblocks. **`FR-HRM-001` is already in
> use** for the leave-approval workflow (`FR-HRM-001-001` = leave requests,
> `-004` = approver chain, `-005` = leave balances, `-007` = used_days,
> `-008` = notifier map). Those references are therefore ambiguous and were
> **not** corrected here, because this task is documentation-only. The correct
> mapping is: *app shell / tab registration* → **this** document.

---

## Need (BABOK What-not-How)

A module's front controller must be able to serve many tabs, several of which it
does not own, and must not be able to serve any of them without a security
decision. HRM previously satisfied the first by hand and the second by
convention.

## Requirement

1. **`hrm.php` shall be a single front controller** for the whole module,
   subclassing `ksfraser\FrontAccounting\Common\App\AbstractFrontController`
   (`src/App/HrmFrontController.php`).
2. **Two-phase bootstrap.** Phase A at global scope: `getSecurityArea()` →
   `$page_security` → `include session.inc` → `add_access_extensions()` →
   `include includes/ui.inc`. Phase B: `run()`.
   `session.inc` and `ui.inc` read `$page_security` / `$path_to_root` as
   **globals in their including scope**; from inside a method they are method
   locals and every nested include resolves against the filesystem root and
   fails silently. This is why the split is structural, not stylistic.
3. **Access enforcement shall fail closed, by construction:**
   a. The constructor's `$fallbackSecurity` is a **required positional
      argument** and is rejected when empty or whitespace-only
      (`FrontControllerSecurityException`).
   b. `run()` is **`final`** — a subclass cannot reorder or omit steps.
   c. `enforceSecurity()` is **`final protected`** — a subclass cannot weaken it.
   d. Enforcement runs **once**, **before** the read/action path is chosen, so
      `?action=save` cannot route around the check the read path would have made.
   e. If `check_page_security()` is unavailable, the request is **refused**, not
      rendered.
4. **A hook-contributed tab shall be checked against its own area.** The
   register hook fires on `boot()`, which needs a session; the area must be
   chosen before `session.inc`. `run()` therefore re-resolves after `boot()` and
   enforces against the contributed tab's own area, not the constructor
   fallback. *(This is the case `CRM` fixed by hand in `4db5a07`; the base class
   makes it structural.)*
5. **Canonical URL shape.**

   | URL | Meaning |
   |---|---|
   | `hrm.php` | default tab (`employees`) |
   | `hrm.php?tab=payroll` | read the payroll tab |
   | `hrm.php?tab=leave&filter_year=2026` | read with a filter applied |
   | `hrm.php?tab=leave&page=3` | tab **+ pager position** |
   | `hrm.php?tab=payroll&action=edit&id=7` | GET pre-load of the entry form |
   | `hrm.php?tab=payroll&action=save` | POST only |
   | `hrm.php?tab=payroll&action=delete&id=7` | POST only (CSRF) |

6. **`tab` and `page` have separate meanings and must never share a parameter.**
   `tab` is the tab selector; `page` is the pager position and is **never**
   consulted as a tab selector. `?view=<tab>` remains accepted as a **deprecated
   alias** for `tab`. `?page=<tab>` is **not** accepted — it was published for
   minutes, never depended upon, and keeping it would re-introduce the exact
   collision the rename removes.
7. **`$_GET` may not be normalised when the host's controllers read the legacy
   parameter.** HRM's controllers return `preserveParams() => ['tab']`, so
   normalising onto `tab` is correct for HRM. (`CrmFrontController` sets
   `normalise_get => false` for the mirror-image reason — its controllers
   default to `['view']` and one of them reads `$_GET['view']` directly.)
8. **Third-party registration.** `HrmAppShell` uses `appId = 'hrm'`, so
   `boot()` fires `hook_invoke_all('hrm_register_tabs', $data)`. Responders append
   `TabRegistration` objects or definition arrays to `$data['tabs']`.

## Non-requirements (explicitly out of scope)

- **CSRF tokens.** `resolveAction()` reads `$_REQUEST`, so `?action=edit&id=7` as
  a GET is a legitimate pre-load. Deciding which verbs are mutations, and whether
  CSRF applies, is the **tab controller's** contract. HRM enforces it locally:
  `action=delete` is POST-only and a GET delete is refused with
  `Delete must be submitted as a POST.` Nothing in the shared base emits or
  verifies a token, and `start_form()` does not either.
- **Record-level authorisation.** `check_page_security()` is page-level.
- **The page title and `<head>` JS.** Left to `getPageTitle()` /
  `getPageJs()`; HRM returns `_("HRM")` and the date-picker bundle for
  `payroll` and `leave` only.

## Implementation notes (recorded because they are easy to undo by accident)

- **`includes/ui.inc` must stay at global scope in `hrm.php`, with
  `['load_ui_includes' => false]`.** FA's `ui.inc` reads `$path_to_root` as a
  global in its including scope. Included from the base class's method it
  resolves every nested include against `/` and fails silently — observed live
  twice, as `Call to undefined function array_selector_row()` mid-render, and as a
  **500 on every HRM page** (`Call to undefined function _()` in
  `includes/sysnames.inc`) when the include was hoisted above `session.inc`,
  because `session.inc` is what loads gettext.
  *Package status:* v1.0.14 seeds `$path_to_root` inside `loadUiIncludes()`, so
  the default is honest again — but HRM's global include remains the proven path
  and is not being changed back.
- **`FrontControllerRoutingException` is effectively unreachable with
  `HrmAppShell`.** `resolveView()` only yields the empty `TabRegistration('', '')`
  when the tab list is empty, and `HrmAppShell` registers twelve tabs in its
  constructor. Confirmed by probe sweep, by byte-identical output, and by
  `testTheRealShellCanNeverTripTheThrow`.
- **PRG target is built from `$_GET`, not `REQUEST_URI`.** FA HTML-escapes
  `$_SERVER['REQUEST_URI']` somewhere on the POST path, turning `?a=1&b=2` into
  `?a=1&amp;b=2` in the `Location` header — a broken target that silently drops
  the rest of the query string. `hrmActionUrl()` builds from `PHP_SELF` + `$_GET`
  via `http_build_query()` and strips `action`, `id` and `view` so the redirected
  GET cannot replay the mutation.

## Acceptance

- ARI-1 → all twelve tabs render; complete footer; no fatal.
- ARI-4 → `?tab=bogus` and `?tab=99` fall back to `employees`, HTTP 200.
- ARI-5 → a tab keyed literally `"3"` remains reachable; `?page=3` never selects it.
- ARI-7 → holding `SA_HRM_LEAVE` but not `SA_ksf_FA_HRMVIEW` is no longer refused at `session.inc`.
- ARI-8 → a hook-registered tab is reachable and checked against its own area
  (`testAHookRegisteredThirdPartyTabIsCheckedAgainstItsOwnAreaNotTheFallback`).
- ARI-9 → output byte-identical across 26 GET routes, pre- and post-adoption.
- Constructor rejects `''` and `'   '`.
- `run()` and `enforceSecurity()` are `final` (asserted by reflection).
- Empty shell throws `FrontControllerRoutingException` rather than rendering blank.
- `?view=leave` still resolves and normalises onto `tab`, with `page` surviving verbatim.

## Verification performed (2026-10-13)

| Check | Result |
|---|---|
| `php -l` host PHP 8.1, container PHP 7.4.33, `tools/lint-php73.sh` (113 files, 7.3.33) | clean |
| `vendor/bin/phpunit` | **OK (284 tests, 741 assertions)** — 252 pre-existing + 32 added |
| 26 GET routes, pre- vs post-adoption | `diff` empty |
| POST mutation routes (leave, payroll) | identical refusal messages old vs new |
| GET delete | still refused |
| `hooks.php` | byte-identical to `HEAD`; 41 methods before and after |
| Deployed vendor | `ksf-fa-common v1.0.13`, `dev: false`, `vendor/bin/` empty, `platform_check.php` requires `>= 7.3` |

## Known gaps

- **Not authenticated end-to-end in a browser for this FR.** Probes were
  authenticated against the live container; the CRM-side equivalent was not.
  `pages/index.php`-style dead scaffolds were not revisited.
- **No CSRF token** — see Non-requirements.
- **A POST probe against `?tab=benefits&action=save` once inserted a junk row**
  into `0_hrm_benefits` (benefit_id 1, empty code/name). Deleted; table
  confirmed back to 0 rows. Mutation probes should only target routes that
  refuse.