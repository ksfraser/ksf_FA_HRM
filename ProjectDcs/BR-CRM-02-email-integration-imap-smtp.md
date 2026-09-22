# BR-CRM-02 — Email Integration (IMAP/SMTP) (C3)

**Modules:** ksf_FA_CRM, substrate BR-COM-04 (periodic import job) /
BR-COM-03 (notify on important mail) / BR-COM-01 (`get_dto` schema)
**Status:** PENDING (design ratified in this BR)
**Built on:** `0_fa_crm_email_accounts` exists — server_host/port/
encryption/username/password/**auto_import/import_frequency/last_import**
(SMTP+IMAP fields present), CRUD via `add/update/delete/get_email_account(s)`.
**No import loop, no sync, no templated sending, no canned replies** (gaps doc
C3: "matches only 'ssl'; no imap/smtp/oauth sync, no templated sends, no
canned replies").

## Business Need / Current State

The account table is already shaped for Mailbox sync (store server, port,
enryption, auto_import, freq, last_import) — the missing half is:
1) an **IMAP import worker** (connect, fetch new, match to contact by
from/subject, store as `0_fa_crm_communications`, mark corresponding lead/
customer thread), run on a BR-COM-04 periodic job honoring
auto_import/import_frequency/last_import; 2) an **SMTP send path** (send via
the account, record as outgoing communication); 3) **templates + canned
replies** (template body with `{token}` substitution over a DTO's schema);
4) **routing rules** (inbox-by-topic → assign to owner/C5 ticket when subject
matches). Oauth is a future concern — v1 is password/session-auth SMTP+IMAP
(FA-level security area protects the stored credentials; no oauth yet).

## Business Requirement

The business requires:

1. **Import worker (IMAP)** — BR-COM-04 job per account (`import_frequency`
   elapsed): connect IMAP (php-imap if available in the FA container; else a
   documented socket fallback refused in v1 — the container has never been
   assumed to carry ext-imap, so v1 **is adapter-based**: a
   `MailboxAdapterInterface` with an `ImapMailboxAdapter` (needs ext-imap)
   and a dry-run/fail-fast when the ext is absent; the job logs 'no-adaptor'
   and no-ops — never crashes the scheduler).
2. **Mail → record mapping** — matched by sender email to `0_crm_persons` /
   `0_fa_crm_contacts` (and `debtor_no`); stored to
   `0_fa_crm_communications` (direction IN, type email) + linked thread
   marker; unmatched mail → leads inbox (C3 triage) not auto-created.
3. **Send path (SMTP)** — compose + send via account; outbound stored as
   communication row (direction OUT, subject/body, contact link) — single
   mailbox of record (C2 campaigns and C5 ticket replies reuse this).
4. **Templates + canned replies** — body text with `{field}` tokens
   substituted from a contact/DTO schema (BR-COM-01 `get_dto` schema → fill
   email, name, company from the contact table; custom tokens per template).
   Canned replies = templates bound to a folder/keyword (support replies).
5. **Routing rules** — simple subject/to rules assign imported mail to owner
   or open a C5 ticket (bridge: this BR defines the rule table; C5 consumes
   it). V1 rule set: `[match-type, pattern, action=assign|ticket]`.
6. **Audit-safe** — every import/send writes a row + status; a failed fetch
   logs and never loses the account from rotation (BR-COM-04 retry/backoff).

## Scope

- In scope: mailbox adapter contract, IMAP import worker (periodic job),
  SMTP send, mail→record mapping, templates/canned replies, routing rules
  (assign/ticket actions), account-ro seed (credential storage protected by
  security area), unit/UAT.
- Out of scope: OAuth2/refresh tokens, calendar-aware mail, attachments as
  S6 documents, full inbox UI (mail list = communications list filtered,
  not a new mailbox UI).

## Design

### Table additions

```sql
CREATE TABLE IF NOT EXISTS `0_fa_crm_mail_templates` (
  `template_id` INT(11) NOT NULL AUTO_INCREMENT,
  `template_name` VARCHAR(80) NOT NULL,
  `subject`    VARCHAR(160) NOT NULL,
  `body`       TEXT NOT NULL,
  `is_canned`  TINYINT(1) NOT NULL DEFAULT 0,  -- canned reply vs send template
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `0_fa_crm_mail_rules` (
  `rule_id`    INT(11) NOT NULL AUTO_INCREMENT,
  `account_id` INT(11) NOT NULL,
  `match_field` VARCHAR(16) NOT NULL,        -- 'subject'|'from'|'to'
  `pattern`    VARCHAR(120) NOT NULL,
  `action`     VARCHAR(12) NOT NULL,         -- 'assign'|'ticket'
  `assign_uid` INT(11) NULL,
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`rule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Import worker (BR-COM-04 job body — one resolver)

```php
function crm_mail_import(AccountRow $a): array {
    // adapter contract: MailboxAdapterInterface { connect($a); fetchUnseen(); }
    $adapter = createAdapter();   // ImapMailboxAdapter or 'no-adaptor:skip'
    $msgs = $adapter->fetchUnseen($a);   // fail-fast if ext missing
    $imported = [];
    foreach ($msgs as $m) {
        $contact = find_contact_by_email($m['from']);   // 0_crm_persons/contacts
        $commId = add_communication([...direction IN, contact_id,
            subject, body, ref_thread]);               // existing add_communication
        apply_rules($a->id, $m, $commId);              // assign/ticket
        $imported[] = $commId;
    }
    $adapter->markAsSeen($a, $msgs);   // one pass; at-least-once tolerant
    return ['imported' => $imported];
}
```

Run per account by the BR-COM-04 `crm.mail.import` recurring job; respects
`auto_import` + `import_frequency` + `last_import`. Failure → job error row,
backoff (BR-COM-04).

### Templates ({token} substitution)

```
{contact.name}, {company.name}, {opportunity.name}, {email} — resolved from
the contact/opportunity/lead DTO via get_dto schema; unknown token -> left
literal (never throws), flagged at save.
```

Send path: fill template → SMTP send → `add_communication(OUT)` row.

## Supporting FRs

- FR-CRM-002-001 MailboxAdapterInterface + ImapMailboxAdapter + no-adaptor
  no-op (never crashes scheduler)
- FR-CRM-002-002 import worker (periodic job, per-account freq/last_import)
- FR-CRM-002-003 mail→record mapping (contact by email; unmatched → triage)
- FR-CRM-002-004 SMTP send + OUT communication row
- FR-CRM-002-005 templates (tokens via get_dto schema) + canned replies
- FR-CRM-002-006 routing rules (assign/ticket actions) — C5 bridge table
- FR-CRM-002-007 unit/UAT (import/send/template/rule flows)

## Acceptance Criteria (UAT)

1. Unit: template fills {contact.name}/{company.name} from a seeded contact
   via get_dto; unknown token stays literal; save flags it.
2. Unit: adapter contract unit-tested with a mock Mailbox; real adapter with
   absent ext-imap returns 'no-adaptor' and the job no-ops cleanly.
3. Unit: import mapping — mail from known contact → communication row (IN,
   contact linked) + thread marker; unknown sender → triage, no auto record.
4. Unit: rules route by subject to assign_uid or create-ticket action; one
   matched rule wins (order).
5. e2e (live FA container, mirror `e2e_hrm_event_windows.php`): point a fake
   mailbox (local IMAP in test harness if ext available, else mock) → job
   imports N → communications rows exist with correct link + direction;
   SMTP send stores OUT row; template render asserted.

## Non-Functional

- PHP 7.3 floor; ext-imap presence detected at runtime (adapter), never
  hard-required; PSR-4 `CRM\Mailbox\`; FA `db_*` only; `0_` SQL literal.
- Credentials encrypted at rest (account password stored via FA-adjacent
  cipher; never logged).

## Related

- BR-COM-04 (import clock), BR-COM-03 (notify on import/rules), BR-COM-01
  (schema tokens), C5 (ticket action + reply templates), C2 (campaign sends
  reuse SMTP path), C8 (activities chain from IN/OUT mail)
- C3 gap; the mailbox-of-record foundation for C2/C5.