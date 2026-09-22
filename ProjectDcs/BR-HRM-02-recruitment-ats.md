# BR-HRM-02 — Recruitment ATS (H1)

**Modules:** ksf_FA_HRM (pages, service, repository, migration), substrate
from ksf_FA_Common (BR-COM-01/02/03), CRM contact types (applicants become
`0_crm_persons` rows tagged `job_applicant` — already registered in
`retag_contact_types.sql`)
**Status:** PENDING (design ratified in this BR)
**Built on:** BR-COM-02 (candidate flow states/history), BR-COM-01 (`get_dto`
schema, engine), BR-COM-03 (inbox for interview/offer events); `hrm_positions`
(HRM-owned shared position table, exists today); `job_applicant` contact type
(already registered).

## Business Need / Current State (verbatim from tree)

`pages/recruitment.php` is an **8-line stub**: "Recruitment module - Coming
Soon … will be implemented in ksf_FA_Recruitment." So we have:

- no vacancies (positions exist in `hrm_positions`, but nothing says "this
  position is open, budgeted, expiring"),
- no applications, no applicant records (a `job_applicant` contact type is
  registered in `retag_contact_types.sql` but unused),
- no interview scheduling, no offer/onboarding hand-over,
- OrangeHRM/Dolibarr/Odoo all have full ATS; gaps doc flags H1 High.

The gap doc's H1 evidence = the stub itself. The substrate gives us the state
machine + DTO + inbox; this BR is the second vertical slice (candidate
pipeline), reusing the same engine and page furniture as BR-HRM-01.

## Business Requirement

The business requires:

1. **Vacancies** — a vacancy table over `hrm_positions`: open posts
   `(position_id, headcount, opens, closes, status)` with a pipeline view
   (draft→published→open→filled→cancelled). Authoring uses the same
   `get_dto` schema machinery; `hrm_positions` stays HRM-owned.
2. **Applications** — a candidate applies to a vacancy: applicant row
   (`0_crm_persons` tagged `job_applicant`, deduped by email) + application
   row `(vacancy_id, person_id, source, stage, submitted_at)`. Inbound
   funnel: web form POST → `get_dto`/create → `after_save` starts the ATS
   process (BR-COM-01 start condition on `application.create`).
3. **Candidate pipeline = BR-COM-02 process** — stages
   `new→screened→interview→decision→offer→hired` (+ `rejected`, `withdrawn`,
   `on_hold`). Each stage transition is a BR-COM-02 edge with guards +
   pre/post actions; `state` + `history` + `allowed_transitions` bubble up to
   the candidate page (BR-COM-02 req 2/3).
4. **Stage actions as function calls** — the edge actions carry the real
   work: `screen` calls a resolver to score/flag; `interview` creates an
   interview slot (Calendar via `broadcast`/`call`), schedules, links the
   interviewer; `offer` creates the offer + triggers approve (same state
   machine second instance over the offer object); `hired` fires
   onboarding hand-over (BR-HRM-05 checklist seed, `broadcast`).
5. **Notifications (BR-COM-03)** — stage changes notify the recruiter/owner;
   `offer_created` notifies the approver chain; `hired` notifies HR. In-app
   inbox only (BR-COM-03 v1); deep links into the candidate/vacancy pages.
6. **Recruiter ownership + audit** — each vacancy/application has an `owner`;
   the pipeline page filters by owner; full history via the state machine's
   append-only rows (BR-COM-02 req 5) — no bespoke audit table.
7. **Security** — `SA_RECRUIT_*` area; stage transitions render only for
   authorized users (BR-COM-02 AC7 pattern).
8. **Reporting** — a small pipeline board (kanban-style buckets by stage,
   time-in-stage from history) that BR-COM-01/02 feed; full HRM reporting is
   BR-HRM-06, this BR only seeds the pipeline board.

## Scope

- In scope: vacancy + application tables/migration, web-inbound form + page,
  candidate page (pipeline, transitions, history, notes), interview stage
  (slot reservation via Calendar + interviewer link), offer stage
  (offer + approve), hired → onboarding `broadcast` seed, pipeline board,
  notification wiring, UAT.
- Out of scope: resume/CV parsing + full document management (S6),
  background-check vendor integration, email channels (BR-COM-03 mail later),
  onboarding checklist content (BR-HRM-05), full ATS reporting (BR-HRM-06).

## Design

### Tables (migration, `0_` literal for FA install)

```sql
CREATE TABLE IF NOT EXISTS `0_hrm_vacancies` (
  `vacancy_id`    INT(11) NOT NULL AUTO_INCREMENT,
  `position_id`   INT(11) NOT NULL,             -- hrm_positions (HRM-owned)
  `headcount`     INT(11) NOT NULL DEFAULT 1,
  `opens_at`      DATE NULL,
  `closes_at`     DATE NULL,
  `state`         VARCHAR(24) NOT NULL DEFAULT 'draft',  -- BR-COM-02 facing
  `owner_uid`     INT(11) NULL,                 -- recruiter (FA user id)
  `created_at`    DATETIME NOT NULL,
  `updated_at`    DATETIME NOT NULL,
  PRIMARY KEY (`vacancy_id`),
  KEY `idx_position_state` (`position_id`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `0_hrm_applications` (
  `application_id` INT(11) NOT NULL AUTO_INCREMENT,
  `vacancy_id`     INT(11) NOT NULL,
  `person_id`      INT(11) NOT NULL,            -- 0_crm_persons (job_applicant)
  `source`         VARCHAR(40) NULL,            -- 'web'|'referral'|'agency'
  `stage`          VARCHAR(24) NOT NULL DEFAULT 'new', -- BR-COM-02 facing
  `owner_uid`      INT(11) NULL,
  `submitted_at`   DATETIME NOT NULL,
  `created_at`     DATETIME NOT NULL,
  `updated_at`     DATETIME NOT NULL,
  PRIMARY KEY (`application_id`),
  KEY `idx_vacancy_stage` (`vacancy_id`, `stage`),
  KEY `idx_person` (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `0_hrm_interviews` (  -- interview slots (append-only)
  `interview_id`  INT(11) NOT NULL AUTO_INCREMENT,
  `application_id`INT(11) NOT NULL,
  `interviewer_uid` INT(11) NOT NULL,           -- FA user id (via Calendar cal)
  `scheduled_at`  DATETIME NOT NULL,
  `outcome`       VARCHAR(24) NULL,             -- 'passed'|'failed'|'cancelled'
  `notes`         TEXT NULL,
  `created_at`    DATETIME NOT NULL,
  PRIMARY KEY (`interview_id`),
  KEY `idx_app` (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Specifics:
- `job_applicant` contact type usage: `create`/`get_dto` on an application
  that has no person → dedupe by email in `0_crm_persons`; if absent, create
  (tagged `job_applicant` via the existing retag SQL); if present unchanged,
  reuse id. This reuses CRM persons, never a parallel applicant table.
- Dedupe is a guard in the submit resolver (BR-COM-01 CalcRegistry): an email
  already applied to the same vacancy → refuse duplicate application row.
- Calendar link = `call`/`broadcast` to Calendar to hold the slot, with the
  interviewer uid + scheduled_at; interview row is the read model of that
  agreement (owned by HRM). No new event system (BR-COM-08/C8 premise).

### Workflow wiring (same furniture as BR-HRM-01)

- `hooks.php` composes template traits once
  (`WorkflowHooksTrait` + `ProvidesDtosTrait` + `ProvidesNotifierTrait`),
  registers `vacancy` + `application` (+ `interview` if it needs state)
  DTO builders (repository-backed, schema via `TableDefinition`).
- Registers two ProcessDefinitions:
  - `vacancy.lifecycle`: draft→published→open→filled/cancelled (auto
    `filled` when the linked `hired` application lands; guard = headcount
    reached).
  - `application.pipeline`: new→screened→interview→decision→offer→hired /
    rejected / withdrawn / on_hold. Edge pre/post actions carry the actual
    stage work (screen resolver, interview-create, offer-create, onboarding
    broadcast).
- Candidate page: `allowed_transitions` rendered as stage buttons; history
  pane = state history (BR-COM-02); notes appended as a plain note column
  (BR-HRM-02 scope; a full commercial-actions feed is C8).
- Inbox (BR-COM-03): stage changes → owner; offer → approver chain;
  hired → HR + owner. Deep links into candidate page.

### Sequence (the vertical slice)

1. Vacancy author (recruiter) publishes → vacancy process
   draft→published→(auto)open when opens_at ≤ today (BR-COM-04 clock job or
   lazy on page load).
2. Web-inbound callable (page/form) POSTs application → dedupe guard →
   create person (job_applicant) if new → create application row →
   `after_save` fires start condition → stage `new`.
3. Recruiter screens → `screened` (resolver sets flags; notify owner).
4. `interview` branch: transition creates interview slot (Calendar) + row;
   interviewer notified with deep link to calendar entry.
5. Decision → `offer`: transition `create`s the offer object (a candidate or
   the offer state machine), which then runs its own approve flow
   (BR-COM-02 second-process instance over the offer); `hired` on approval →
   broadcast to BR-HRM-05 onboarding + vacancy `filled` (headcount check).
6. Pipeline board renders buckets by current stage; time-in-stage from
   history (no extra table).

## Supporting FRs

- FR-HRM-002-001 vacancy table/migration + publish flow (`opens_at` gate)
- FR-HRM-002-002 application table/migration + web-inbound form + dedupe
  guard (email × vacancy)
- FR-HRM-002-003 job_applicant person tagging (CRM persons reuse; never a
  parallel applicant table)
- FR-HRM-002-004 `vacancy.lifecycle` + `application.pipeline` Process-
  Definitions in hooks.php
- FR-HRM-002-005 candidate page (pipeline, allowed-transitions, history,
  notes) + security-area gates
- FR-HRM-002-006 interview stage (Calendar slot + interviewer notify)
- FR-HRM-002-007 offer stage (offer object + second approve process; hired →
  onboarding broadcast; vacancy auto-filled)
- FR-HRM-002-008 notification wiring (owner/approver/HR, deep links)
- FR-HRM-002-009 pipeline board (buckets + time-in-stage) + unit/UAT

## Acceptance Criteria (UAT)

1. Unit: vacancy draft→published→open once `opens_at` passed; second publish
   refused (no-op).
2. Unit (dedupe): same email to same vacancy → rejected application row with
   message; new email → person created (job_applicant tag) + application row.
3. Unit (pipeline): screened→interview creates a slot row + Calendar link +
   notify interviewer; decision→offer creates offer; approval fires hired →
   vacancy auto-`filled` at headcount, `rejected` keeps vacancy open.
4. Unit (access): user without `SA_RECRUIT_*` sees no stage buttons, direct
   transition refused.
5. e2e (live FA container, mirrors `e2e_hrm_event_windows.php`): seed
   position + vacancy, post an application by email → person + application;
   run to `hired`; assert vacancy `filled`, applicant `0_crm_persons` row
   tagged `job_applicant`, notification rows for owner + HR, history rows.

## Non-Functional

- PHP 7.3 floor; PSR-4 `HRM\Recruitment\`; FA `db_*` only; `0_` SQL literal.
- No CV parsing/storage in v1 (S6); a `cv_note`/attachment ref column is
  reserved but not read.
- In-app notifications only (mail via BR-COM-03 later).

## Related

- BR-HRM-01 (same engine furniture — this is pattern #2), BR-COM-02
  (pipeline state machine), BR-COM-01/03 (DTO + inbox), BR-HRM-05
  (onboarding broadcast consumer), BR-COM-04 (opens_at/offer-expiry jobs)
- H1 gap; feeds H6 offboarding symmetry, C1 funnel (candidate→lead
  relevance), C8 activity chaining (applicant as contact activity subject).