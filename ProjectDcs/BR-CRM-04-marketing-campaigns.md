# BR-CRM-04 — Marketing Campaigns (C2)

**Modules:** ksf_FA_CRM, substrate BR-COM-03/BR-CRM-02 (mail send reuse),
BR-COM-04 (scheduled sends), BR-COM-01 (`get_dto`)
**Status:** PENDING (design ratified in this BR)
**Built on:** There is **no campaign page/table today** (gaps doc C2:
"Signature feature of SuiteCRM; absent in our tree"). Reuses BR-CRM-02 SMTP
send path + mail templates, `0_crm_persons`/`0_fa_crm_contacts` as the list
source, and BR-CRM-01's pipeline for campaign→lead attribution
(`leads.campaign_id` and `opportunities.campaign_id` fields already exist).

## Business Need / Current State

No campaign entity. SuiteCRM's signature feature — targeted lists, templates,
tracking, attribution to funnel — is entirely absent. C2 adds the campaign as
first-class data over the C3 mailbox so a campaign's email is a normal
outgoing communication (single mailbox of record, C2-referenced from
BR-CRM-02).

## Business Requirement

The business requires:

1. **Campaign entity** — `(name, type (email/event), status: draft/
   scheduled/sending/completed/archived, start/end dates, budget)`; draft
   allows edit, scheduled freezes content (versioned at send).
2. **Target lists** — saved contact/l segment queries (a criteria set, same
   shape as BR-COM-01 `get_dto_list` criteria) → snapshot rows of
   `contact_id` at send time (the list is a freeze, not a live query, so the
   "who was sent" audit is stable).
3. **Template + send** — one send per campaign using a BR-CRM-02 template
   (tokens per contact); dispatcher runs via BR-COM-04 job or manual
   "Send now" that loops the SMTP path (rate-limited, per-account).
4. **Tracking** — per-contact send row (sent_at, delivered/opened/clicked
   booleans where the mailbox/proxy provides it — v1 logs send + optional
   open pixel, clicked from a tracking link param); opens/clicks are
   data rows, not required to work in every mail host.
5. **Attribution** — inbox-rule/campaign: a campaign's `leads.campaign_id`
   /`opportunities.campaign_id` populated when a lead that sent campaign
   mail converts (BR-CRM-01 conversion already carries campaign_id through);
   response = an IN communication whose thread matches the campaign send.
6. **Reporting** — sent/opened/clicked/response counts + conversion-to-lead
   /opportunity/revenue per campaign (reuses BR-CRM-01 forecast + C7 KPI
   resolver).

## Scope

- In scope: campaign entity + target-list snapshot + freeze, send dispatcher
  (manual + BR-COM-04 job), tracking rows (send/open/click), attribution
  link on conversion, simple report block.
- Out of scope: A/B testing, drip/automation sequences (C8 chaining),
  landing pages, list hygiene/opt-out registry UI (a suppression set
  v1-minimal: global opt-out email set read at send).

## Design

### Tables

```sql
CREATE TABLE IF NOT EXISTS `0_fa_crm_campaigns` (
  `campaign_id` INT(11) NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(120) NOT NULL,
  `type`        VARCHAR(16) NOT NULL DEFAULT 'email',
  `status`      VARCHAR(16) NOT NULL DEFAULT 'draft',
  `start_at`    DATETIME NULL,
  `end_at`      DATETIME NULL,
  `budget`      DECIMAL(15,2) NOT NULL DEFAULT 0,
  `template_id` INT(11) NULL,                 -- BR-CRM-02 template
  `account_id`  INT(11) NOT NULL,             -- send via this mailbox
  `criteria`    TEXT NULL,                    -- get_dto_list criteria (JSON)
  `suppress`    TEXT NULL,                    -- opt-out email set
  `created_by`  INT(11) NULL,
  `created_at`  DATETIME NOT NULL,
  PRIMARY KEY (`campaign_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `0_fa_crm_campaign_sends` (  -- append-only
  `send_id`   INT(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` INT(11) NOT NULL,
  `contact_id`  INT(11) NOT NULL,
  `sent_at`     DATETIME NOT NULL,
  `opened_at`   DATETIME NULL,
  `clicked_at`  DATETIME NULL,
  `response_comm_id` INT(11) NULL,           -- IN communication = reply
  PRIMARY KEY (`send_id`),
  KEY `idx_campaign` (`campaign_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Dispatcher

- Snapshot: at send, expand criteria → frozen `campaign_sends` rows
  (contact_id) minus suppression. One send per contact.
- Loop through SMTP path (BR-CRM-02 send) updating sent_at; rate limit
  (e.g. N/minute; configurable) honoring BR-COM-04 job cadence.
- Tracking link param: outbound body's URLs rewritten to
  `?campaign=<id>&contact=<id>` when the pixel/tracking is enabled; open
  pixel `0_fa_crm_campaign_sends` update on GET (idempotent).
- Manual 'Send now' and scheduled job share the same loop (DRY).

## Supporting FRs

- FR-CRM-004-001 campaign entity CRUD + status freeze
- FR-CRM-004-002 target-list snapshot (get_dto_list criteria → frozen rows)
  + suppression
- FR-CRM-004-003 send dispatcher (SMTP reuse, rate limit, job + manual)
- FR-CRM-004-004 tracking rows (sent/opened/clicked/pixel, idempotent)
- FR-CRM-004-005 attribution (leads/opportunities campaign_id on conversion;
  reply-thread response mark)
- FR-CRM-004-006 campaign report (counts + conversion/revenue via C1/C7)
- FR-CRM-004-007 unit/UAT

## Acceptance Criteria (UAT)

1. Unit: target list snapshot equals get_dto_list expansion; suppression
   removes opt-outs; frozen rows stable after a criteria change.
2. Unit: dispatcher marks each send; rate limit honored; row-level failure
   (bad contact email) logged, others continue (BR-COM-04 style).
3. Unit: pixel/click update idempotent (no double timestamps on re-GET).
4. Unit: conversion of a campaign-attributed lead sets campaign_id on the
   opportunity; reply-thread IN comm marks response.
5. e2e (live FA container, mirror `e2e_hrm_event_windows.php`): seed contacts
   + template → create campaign → Send now → N send rows; open/click sim → 
   timestamps; attribution on a conversion; report counts match.

## Non-Functional

- PHP 7.3; PSR-4 `CRM\Campaign\`; FA `db_*` only; `0_` SQL literal;
  reuses BR-CRM-02 mailbox — never a second send path.
- Sends are append-only (audit), list snapshots frozen (no live-query drift).

## Related

- BR-CRM-02 (mailbox + templates), BR-COM-04 (scheduled sends),
  BR-CRM-01/C7 (attribution + reporting), BR-COM-01 (list criteria)
- C2 gap; campaign→lead→opp funnel attribution is the marketing→pipeline
  bridge the other suites have.