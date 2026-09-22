# BR-CRM-06 — Contracts & Renewal Lifecycle (C6)

**Modules:** ksf_FA_CRM, substrate BR-COM-02 (contract process) / BR-COM-04
(renewal reminders) / BR-COM-03 (notify)
**Status:** PENDING (design ratified in this BR)
**Built on:** `0_fa_crm_account_relationships` and `org_chart.php`/
`account_relationships.php` exist (relationship data, no lifecycle);
`0_fa_crm_persons`/`0_fa_crm_contacts` as parties (SuiteCRM/Odoo both track
contracts w/ renewal). gaps doc C6: relationship rows exist; **no contract
lifecycle / renewal tracking.**

## Business Need / Current State

A relationship (customer↔supplier↔partner) is a static row; contracts with
terms, renewal dates, and obligations are absent. C6 adds contracts as
first-class records with a BR-COM-02 lifecycle and renewal automation, so
"this agreement ends/renews" is never a surprise (SuiteCRM/Odoo parity).

## Business Requirement

The business requires:

1. **Contract entity** — `(contract_no, title, type, account_relationship_id,
   debtor/supplier, start_date, end_date, auto_renew, terms, value)`; a
   contract links to the existing relationship row (no new party store).
2. **Lifecycle** — `contract.lifecycle`: `draft→active→renewed|expired|
   terminated`. Guards: active requires start_date ≤ today; renewed appends
   a renewal record (linked prior id) extending end_date; terminated
   requires reason + effective date. History (approvals, who) via BR-COM-02.
3. **Renewal automation** — BR-COM-04 job: within N days of end_date
   (config) → `notify` owner + finance (BR-COM-03) with renewal hint; if
   `auto_renew` → renew automatically at end (creates renewal record,
   extends end_date by the term) and notify; else leaves `active` with a
   pending-renewal flag (dashboard surfaces it).
4. **Contract artifacts** — PDF/scan reference column (S6 later; v1 = a
   `doc_ref` string/link, no storage) — contract page shows it.
5. **Obligations view** — value + dates + auto_renew surfaced on the
   relationship/contract page; C7 dashboard gets a "contracts expiring /
   renewals due" block from this BR's resolver.
6. **Audit** — renewals/terminations append-only (prior ids linked), mirror
   BR-007/BR-COM-02 discipline.

## Scope

- In scope: contract entity + CRUD, lifecycle process + guards, renewal job
  (notify + auto-renew), pending-renewal flag, relationship link,
  dashboard block, unit/UAT.
- Out of scope: document storage (S6), legal clause versioning, payment
  schedule linkage to FA invoicing (a follow-on BR-CRM-06.1 could add).

## Design

### Tables

```sql
CREATE TABLE IF NOT EXISTS `0_fa_crm_contracts` (
  `contract_id`  INT(11) NOT NULL AUTO_INCREMENT,
  `contract_no`  VARCHAR(40) NOT NULL,
  `title`        VARCHAR(200) NOT NULL,
  `type`         VARCHAR(40) NULL,
  `relationship_id` INT(11) NULL,          -- 0_fa_crm_account_relationships
  `debtor_no`    VARCHAR(20) NULL,
  `start_date`   DATE NOT NULL,
  `end_date`     DATE NOT NULL,
  `auto_renew`   TINYINT(1) NOT NULL DEFAULT 0,
  `renew_term_days` INT(11) NULL,
  `value`        DECIMAL(15,2) NOT NULL DEFAULT 0,
  `state`        VARCHAR(16) NOT NULL DEFAULT 'draft', -- BR-COM-02 facing
  `pending_renewal` TINYINT(1) NOT NULL DEFAULT 0,
  `doc_ref`      VARCHAR(255) NULL,
  `terminate_reason` VARCHAR(255) NULL,
  `created_at`   DATETIME NOT NULL,
  PRIMARY KEY (`contract_id`),
  UNIQUE KEY `idx_contract_no` (`contract_no`),
  KEY `idx_end_date` (`end_date`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `0_fa_crm_contract_renewals` (  -- append-only chain
  `renewal_id`   INT(11) NOT NULL AUTO_INCREMENT,
  `contract_id`  INT(11) NOT NULL,
  `prior_id`     INT(11) NULL,              -- chained prior renewal
  `new_end_date` DATE NOT NULL,
  `by_uid`       INT(11) NULL,              -- NULL = auto-renewed by job
  `created_at`   DATETIME NOT NULL,
  PRIMARY KEY (`renewal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Process + renewal job

```
contract.lifecycle: draft -> active -> renewed|expired|terminated
start: 'draft' on create
user:  draft->active [guard: start_date <= today]
job:   every check (BR-COM-04): 
       if within(N days) and auto_renew  -> renew: append renewal, extend
                                            end_date, notify
       else if within/expired            -> pending_renewal=1, notify owner
user:  active->terminated [reason required]; active->expired (auto, end passed)
       renewed goes back to active with extended end (renew chain row)
```

## Supporting FRs

- FR-CRM-006-001 contract entity + CRUD + relationship link + doc_ref
- FR-CRM-006-002 contract.lifecycle process + guards (active/terminate/expire)
- FR-CRM-006-003 renewal chain (append-only, prior linked) + auto-renew
  resolver
- FR-CRM-006-004 renewal-reminder job (BR-COM-04 + BR-COM-03 notify)
- FR-CRM-006-005 pending-renewal flag + C7 dashboard block
- FR-CRM-006-006 unit/UAT

## Acceptance Criteria (UAT)

1. Unit: draft→active guard enforces start_date; terminated requires reason.
2. Unit: auto-renew within window → renewal row linked to prior, end_date
   extended, notify sent, pending flag cleared.
3. Unit: non-auto within window → pending_renewal=1 + owner notify exactly
   once (job idempotent).
4. Unit: expired after end_date without renewal → state expired + flag.
5. e2e (live FA container, `e2e_hrm_event_windows.php` mirror): seed contract
   ending in N days (auto_renew=1) → run job → renewal row + extended end +
   notice; dashboard block shows the renewed/pending states correctly.

## Non-Functional

- PHP 7.3; PSR-4 `CRM\Contract\`; FA `db_*` only; `0_` SQL literal;
  renewal chain is append-only (never UPDATE the prior row's end_date out
  from under history).

## Related

- BR-COM-02/04/03 (states, renewal clock, notify), BR-CRM-01 (relationship↔
  opportunity link), C7 (expiring contracts block)
- C6 gap; complements H9 (employment contracts) on the commercial side —
  both are S6 (artifacts)-adjacent but track lifecycle independently of
  documents.