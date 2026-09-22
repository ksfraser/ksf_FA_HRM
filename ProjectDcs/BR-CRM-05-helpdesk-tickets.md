# BR-CRM-05 — Helpdesk / Cases / Tickets (C5)

**Modules:** ksf_FA_CRM, substrate BR-COM-02 (ticket states) / BR-COM-03
(notify) / BR-CRM-02 (mail routing + reply templates)
**Status:** PENDING (design ratified in this BR)
**Built on:** There is **no ticket entity today** (gaps doc C5: "Absent.").
Consumes BR-CRM-02's `crm_mail_rules` (rule action `ticket` already defined)
and replied-with-canned-reply path, plus BR-COM-01 `get_dto` for the ticket
record.

## Business Need / Current State

SuiteCRM (service), Dolibarr (tickets), Odoo (helpdesk) all route requests and
track resolution; we have none. C5 builds the ticket as a first-class record
that is **born from email rules** (BR-CRM-02), or manually from a customer
page, walks a BR-COM-02 state machine, and replies via the same mailbox.

## Business Requirement

The business requires:

1. **Ticket entity** — `(ticket_id, account_id, subject, body, priority,
   status, assignee_uid, contact/debtor, category, sla_due_at)`. Created
   from: BR-CRM-02 mail rule action `ticket`, manual new-ticket page, or a
   follow-up note on an existing thread.
2. **State machine** — `ticket.lifecycle`: `new→triaged→in_progress→
   resolved|closed` (+`reopened`, `on_hold`); guards: resolve requires
   resolution note; reopen allowed only from resolved; close after resolve.
   History + allowed transitions (BR-COM-02) on the ticket page; SLA breach
   ≠ state, it's a data flag for the dashboard/report.
3. **Routing** — new ticket auto-assigns by category/queue (a rule/mapping:
   category→assignee_uid); escalate (reassign) is a guarded transition
   keeping the prior owner visible in history.
4. **Canned replies** — BR-CRM-02 templates bound as quick replies
   (is_canned), replied on the ticket → OUT communication row + thread link.
   A reply appends the ticket (activity). Any IN mail on the thread reopens
   or appends.
5. **SLA + reminders** — `sla_due_at` set per priority (config data);
   BR-COM-04 job flags nearing-breach and notifies assignee + manager
   (BR-COM-03). Breach written as a history/tick, not a state change.
6. **Reporting** — ticket counts by status/priority/assignee, SLA-hit%,
   reopen rate, first-response time — resolver shared with C7 dashboard.

## Scope

- In scope: ticket entity + CRUD, state machine + guards, routing by
  category, mail-rule born tickets + thread append, canned-reply replies,
  SLA due + remind job, resolver reporting, unit/UAT.
- Out of scope: multi-channel beyond email, KB/self-service portal (Odoo;
  possibly a later self-service), rich attachments as S6 artifacts.

## Design

### Tables

```sql
CREATE TABLE IF NOT EXISTS `0_fa_crm_tickets` (
  `ticket_id`    INT(11) NOT NULL AUTO_INCREMENT,
  `account_id`   INT(11) NULL,               -- source mailbox (if born from mail)
  `debtor_no`    VARCHAR(20) NULL,
  `contact_id`   INT(11) NULL,
  `thread_key`   VARCHAR(80) NULL,           -- original mail thread ref
  `subject`      VARCHAR(200) NOT NULL,
  `body`         TEXT NULL,
  `category`     VARCHAR(60) NULL,
  `priority`     VARCHAR(16) NOT NULL DEFAULT 'normal',
  `state`        VARCHAR(16) NOT NULL DEFAULT 'new',   -- BR-COM-02 facing
  `assignee_uid` INT(11) NULL,
  `sla_due_at`   DATETIME NULL,
  `created_at`   DATETIME NOT NULL,
  `updated_at`   DATETIME NOT NULL,
  PRIMARY KEY (`ticket_id`),
  KEY `idx_state_priority` (`state`, `priority`),
  KEY `idx_assignee` (`assignee_uid`),
  KEY `idx_sla` (`sla_due_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Process + routing + SLA

```
ticket.lifecycle: new -> triaged -> in_progress -> resolved -> closed
   (+ on_hold, reopened for resolved)
start: 'new' on ticket create (mail rule or manual)
auto:  new->triaged [call route: category+priority -> assignee_uid]
user:  triaged->in_progress (Start work; appends 'claimed' history)
user:  in_progress->resolved (resolution note required — guard)
user:  resolved->closed (user) / resolved->reopened (IN mail on thread)
post:  [reply] -> reuses BR-CRM-02 send + appends ticket activity
SLA:   sla_due_at = now + priority-based hours (config data);
       BR-COM-04 job: <=24h left & open -> notify assignee+manager (once)
```

Routing table = category→queue/assignee mapping (data, admin-edited), same
shape as `crm_mail_rules` (BR-CRM-02).

### Birth from mail

BR-CRM-02 import: rule action `ticket` creates the ticket (subject/body/from→
contact/thread_key), and replies via canned-reply templates reference the
same `thread_key` so IN replies append.

## Supporting FRs

- FR-CRM-005-001 ticket entity + CRUD + manual/new-from-mail creation
- FR-CRM-005-002 ticket.lifecycle process + guards (resolution note,
  reopen/close rules)
- FR-CRM-005-003 routing (category→assignee) + escalation history
- FR-CRM-005-004 thread append (IN mail appends/attaches; mail->rule token)
- FR-CRM-005-005 canned-reply send (BR-CRM-02) + OUT comm row
- FR-CRM-005-006 SLA due + breach reminder job (BR-COM-04)
- FR-CRM-005-007 reporting resolver (status/priority/SLA-hit/reopen/first-
  response) → C7
- FR-CRM-005-008 unit/UAT

## Acceptance Criteria (UAT)

1. Unit: mail-rule `ticket` action creates ticket with thread link; response
   on same thread reopens/attaches not duplicate-create.
2. Unit: resolve without note refused; reopen from resolved ok; close only
   from resolved/closed.
3. Unit: routing sets assignee from category mapping; escalate records prior
   owner in history.
4. Unit: SLA job notifies ≤24h-before-due exactly once; breach written as
   history not state.
5. e2e (live FA, `e2e_hrm_event_windows.php` mirror): route a mail → ticket
   born; triage→in_progress→resolve(canned reply) → assert OUT comm row,
   resolution note, SLA untouched; report rows match expected counts.

## Non-Functional

- PHP 7.3; PSR-4 `CRM\Helpdesk\`; FA `db_*` only; `0_` SQL literal;
  mailbox reuse only (never a second send/import path).

## Related

- BR-CRM-02 (birth + replies + rules), BR-COM-02/03/04 (states/inbox/SLA
  job), C7 (dashboard), C8 (ticket activities in the chain)
- C5 gap; completes the service triangle with C3 (mailbox) and C4
  (quote→order) — post-sales service loop.