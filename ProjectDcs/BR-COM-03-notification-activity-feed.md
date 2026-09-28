# BR-COM-03 — Notification + Activity-Feed Subsystem

**Modules:** ksf_FA_Common (contract + engine), any module publishing events
(HRM, CRM, Calendar, ProjectManagement, Timesheets), UI host (see decision)
**Status:** PENDING (design ratified in this BR)
**Built on:** BR-COM-01 (`broadcast` DO verb, `get_dto`), BR-COM-02 (state
changes, `leave_approved` broadcast), BR-007 (event-close protocol)

## Business Need

BR-COM-02 introduced approvals and states, but nothing tells a **user** that a
record changed, that their approval is awaited, or that their leave was
rejected. Today that knowledge lives entirely inside the FA app session flow,
or is chased by phone. Every suite in the gap analysis (SuiteCRM Activity
Stream, Odoo chatter, Dolibarr agenda/events, OrangeHRM leave notifications)
treats an **activity feed + per-user notification inbox** as table stakes. This
BR provides the subsystem, sitting on the already-defined `broadcast` verb:
workflow post-actions fire events; the feed subscribes once; users get an
inbox.

## Business Requirement

The business requires:

1. **Least-effort publish**: any module/step can publish a notification with
   one call — `notify(recipients, type, payload, ref)` — inside a BR-COM-01
   `broadcast` action. The subscriber engine, not the publisher, owns
   persistence, routing, and presentation.
2. **Per-user inbox**: each FA user sees their unread notifications
   (badge + list page). Read/unread; dismiss (soft-delete). Nothing leaves
   the FA session until viewed.
3. **Routing targets**: recipients expressed as — user id(s), FA security role
   (everyone with `SA_LEAVE_APPROVE`), a DTO's `owner`/`reports_to` field
   (via `get_dto` schema), or `@all` for system broadcasts. Resolved at
   fire-time via FA security areas (no new auth — BR-COM-02 AC7 semantics).
4. **Type-driven rendering**: notification types are registered per module
   (title template, body/fields, deep link to the source record page, icon),
   matching the `get_dto` schema pattern — the inbox renders any module's
   notifications without knowing them.
5. **Append-only, audit-safe**: rows are INSERT-only (`0_ksf_notification_inbox`);
   `read_at`/`dismissed_at` update the owner row only. Mirrors BR-007 /
   BR-COM-02 append-only discipline.
6. **Silence is safe**: unknown type, unresolvable recipient, or renderer
   failure → row stored with raw payload, rendering degrades to a JSON dump;
   never throws into a step chain (BR-COM-01 req 5).
7. **Subscription prefs (v1 minimal)**: per-user mute-by-type + digest
   flag; default all-on. Channel escalation (email) is out of scope for v1
   (hooks to `ksf_FA_Mail`/EmailManager later).

## Scope

- In scope: publish API, inbox persistence + UI, recipient resolution, type
  registry, rendering, prefs.
- Out of scope: email/SMS delivery, digest jobs (BR-COM-04), feed *on the
  record page* (renders via existing page; a full embedded timeline is a
  follow-on), notification templates as documents.

## Design

### Table (append-only, `0_` literal for FA install)

> **Naming decision (2026-09):** this spec originally named the inbox
> `0_ksf_notifications`, but that name is already taken by the existing
> dispatch/outbox table (`ksf_notifications`, used by
> `Common\Notification\NotificationRepository` in the same `install.sql`). Per
> user decision the inbox lives in **`0_ksf_notification_inbox`** with prefs in
> **`0_ksf_notification_prefs`** and the @all watermark in
> **`0_ksf_notification_watermark`**. The outbox subsystem is untouched. If this
> rename propagates anywhere else, keep the new names.

```sql
CREATE TABLE IF NOT EXISTS `0_ksf_notification_inbox` (
  `id`            INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `recipient_uid` INT(11) NOT NULL,          -- FA user id (0 = @all placeholder)
  `type`          VARCHAR(60) NOT NULL,      -- 'hrm.leave_approved', ...
  `payload`       TEXT,                      -- JSON body fields
  `ref`           VARCHAR(120) NULL,         -- 'hr:leave_request:42' for deep link
  `created_at`    DATETIME NOT NULL,
  `read_at`       DATETIME NULL,
  `dismissed_at`  DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_num_recipient` (`recipient_uid`, `read_at`, `created_at`)
);
```

### Publish API (engine)

```php
$notifier = Common\Notification\NotifierFactory::create();
$notifier->notify(
    ['roles' => ['SA_LEAVE_APPROVE']],      // routing target
    'hrm.leave_approved',                    // registered type
    ['request_id' => 42, 'person' => 'A. Employee'],
    'hr:leave_request:42'                    // deep link ref
);
```

Callable from any BR-COM-01 DO verb as `['call','common.notifier','notify',
...]` — so workflow post-actions publish with the same step-row authoring as
every other action (no module code).

### Registration (per module, trait-assisted)

```php
// module hooks.php responder for hook_invoke_all('notify', $payload)
// via Common\Notification\ProvidesNotifierTrait
$registry->register('hrm.leave_approved', [
    'title'   => 'Leave approved',
    'body'    => ['request_id' => 'Request #%s', 'person' => 'For %s'],
    'link'    => ['href' => 'modules/ksf_FA_HRM/pages/leave.php',
                  'id_param' => 'id', 'ref' => 'hr:leave_request:%request_id%'],
    'icon'    => 'icon_leave',
]);
```

Renderer = schema (labels + param substitution) + optional `callable` for
custom layout; absent callable → default renderer (title + body + link).

### Inbox UI (host decision)

- **Where**: `ksf_FA_Common` is a package, not an FA page host (AGENTS
  §activation). v1 hosts the inbox as a small page in a named host module;
  **decision needed**: dedicate `ksf_FA_ActivityFeed` (new) vs fold into
  RBAC's user center (existing login/user admin). Recommended: fold into RBAC
  for v1 (users already exist there; zero new module surface), extract later
  if the feed grows an embedded per-record timeline.
- **Contents**: badge count (top bar), list page (unread first, paginate),
  mark-all-read, dismiss, type filter. Deep link renders the source page.

### Recipient resolution

At fire-time: expand `roles`/`users`/`all`/`dto-field` against FA security
areas → concrete user ids → one row per recipient. `@all` is expanded
lazily (0 placeholder row; materialized on first read poll — cheap, since
`@all` is rare and reads are where we render).

### Supporting FRs

- FR-COM-03-001 `notify()` API + payload/ref contract (insert-only)
- FR-COM-03-002 routing resolution (roles/users/dto-field/@all → FA users)
- FR-COM-03-003 type registry + renderer (schema/callable, default renderer,
  graceful JSON fallback)
- FR-COM-03-004 inbox badge + list page (read/unread/dismiss/filter), host
  module mounting
- FR-COM-03-005 prefs v1 (mute-by-type, digest flag)
- FR-COM-03-006 e2e: workflow approve → notification row for the approver →
  badge reflects it → deep link opens the leave page (live FA container,
  mirrors `e2e_hrm_event_windows.php`)

## Acceptance Criteria (UAT)

1. Unit: `notify()` from a step row creates one row per expanded recipient;
   a second call for the same event creates a second row (append-only).
2. Unit: unknown `type` returns/renders raw JSON payload, no throw; bad
   recipient list resolves to zero rows, no throw.
3. Rule: user with `SA_LEAVE_APPROVE` sees the `hrm.leave_approved`
   notification; a user without it never sees a row (role resolution test).
4. e2e: BR-COM-02 leave flow at `pending→approved` → approver's badge +1,
   list shows title/body, deep link opens `leave.php?id=42`.
5. Prefs: muted type is not rendered (rows still stored, per append-only).

## Non-Functional

- PHP 7.3; PSR-4 `Common\Notification\`; payload JSON always storable.
- Perf: inbox query indexed by `(recipient_uid, read_at, created_at)`; role
  expansion cached for the request.
- Security: rows keyed by `recipient_uid`; only owner (or `SA_*` admin) reads
  their inbox; deep links reuse existing page security.

## Related

- BR-COM-02 (publishes state-change notifications via `broadcast`/`call`)
- BR-COM-01 (`get_dto` for dto-field recipients; step-row authoring)
- S3 gap; feeds H3 leave, C4 quote approval, C5 helpdesk triage
- BR-COM-04 (digests + scheduled reminder polls) — declared out of scope v1