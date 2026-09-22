# BR-CRM-01 — Pipeline Funnel & Forecast (C1)

**Modules:** ksf_FA_CRM, substrate BR-COM-02 (stage machine) / BR-COM-01
(`get_dto` schema) / BR-COM-03 (stage-change notices)
**Status:** PENDING (design ratified in this BR)
**Built on:** `0_fa_crm_opportunities` already has `stage`, `probability`,
`estimated_value`, `expected_close_date`, `lost_reason`, `won_notes`,
`lead_id`, `quote_id`, `actual_close_date` (real funnel data, no UI), and
`0_fa_crm_leads` has `lead_status`, `rating`, `converted_date`,
`converted_to_debtor_no`. `leads.php`(212) + `opportunities.php`(149) are
status-labeled CRUD with no kanban, no stage-probability reconciliation, no
weighted forecast.

## Business Need / Current State

The data model already smells like SuiteCRM/Odoo funnel fields — but the page
just CRUDs rows. C1 needs: a **kanban pipeline** (columns = stages,
cards = opportunities), **stage probability** as a config (not a stale number
stuck at insert), **weighted forecast** (sum(value × probability-by-stage)),
**win/loss reasoning** (nice: lost_reason/won_notes are collected), and
**conversion** from lead→opportunity (the fields exist; convert_lead.php
exists) so the funnel is contiguous from lead to close. gaps doc: "No kanban,
stage probability, weighted forecast."

## Business Requirement

The business requires:

1. **Stage model as config data** — a stage order per pipeline type
   (`0_fa_crm_pipelines`: name, sort_order, probability %, behaviors
   "won"/"lost" flags). `opportunities.stage` stays on the row (compat) but
   `probability` is **recomputed from the stage** (single source of truth) —
   the insert-time probability becomes a confidence override only.
2. **Kanban pipeline UI** — `opportunities.php` gains a kanban view (columns
   per stage, drag-and-drop in v2 — v1 = move via stage select/buttons),
   cards show name, customer, value, expected_close, owner. No asset lib:
   CSS flex columns.
3. **Weighted forecast** — a forecast block: per-stage sum(value), weighted
   sum = Σ(value × stage_probability), expected-close-month distribution,
   and a "commit vs pipeline" split (probability ≥ 70% = commit tier).
   Resolver computed, shared with BR-CRM-07 dashboard (DRY).
4. **Stage transitions via BR-COM-02** — `opportunity.pipeline` process
   (qualification→developed→proposal→negotiation→won/lost, honoring
   pipeline config order). `won` guard: a quote exists (quote_id) or a
   won_note; `lost` requires lost_reason (else refused). Stage history
   (reasons, who/when, value at stage) via the state machine rows.
5. **Lead→opportunity contiguous** — `convert_lead.php` (exists) wires lead
   conversion into opportunity creation (converted_date +
   converted_to_debtor_no written) and auto-advances lead_status to
   'converted'; forecast includes converted opportunities.
6. **Stage-change notices** — owner notified on stage move + won/lost
   (BR-COM-03); won also broadcasts `opportunity_won` (C8 activity chaining
   consumer + CRM dashboard KPI feed).

## Scope

- In scope: pipeline config tables, kanban view, weighted forecast resolver +
  block, BR-COM-02 stage process + guards, lead→opp conversion wiring (over
  convert_lead.php), notices, unit/UAT.
- Out of scope: reporting/SLA analytics beyond forecast (BR-CRM-07),
  drag-drop kanban polish, multi-currency.

## Design

### Tables

```sql
CREATE TABLE IF NOT EXISTS `0_fa_crm_pipelines` (   -- stage order/config (data)
  `pipe_id`    INT(11) NOT NULL AUTO_INCREMENT,
  `pipe_type`  VARCHAR(40) NOT NULL,          -- 'sales'|'service'...
  `stage`      VARCHAR(30) NOT NULL,          -- matches opportunities.stage
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  `probability` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `is_won`     TINYINT(1) NOT NULL DEFAULT 0,
  `is_lost`    TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`pipe_id`),
  UNIQUE KEY `idx_type_stage` (`pipe_type`, `stage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Forecast resolver (single definition)

```
per stage:      count, sum(estimated_value)
weighted_stage = sum(value) * probability(stage)
commit_tier    = rows with probability >= 70
total_weighted = Σ weighted_stage
by_month       = weighted grouped by expected_close_date month
```

Stored as CalcRegistry resolver `crm.pipeline.forecast(type, as_of)` — used by
kanban page, BR-CRM-07 dashboard, and (later) portal KPI. One pass over
`opportunities` filtered `!inactive AND stage not in lost/Won-closed`.

### Pipeline process (BR-COM-02)

```
states from 0_fa_crm_pipelines order (default seeded: qualification ->
developed -> proposal -> negotiation -> won/lost)
start: 'qualification' on opportunity create (lead conversion too)
user:  next/prev stage (buttons in kanban card)
guard: won  -> quote_id set OR won_notes provided (else refused)
       lost -> lost_reason required
post:  stage change -> notify owner; won -> broadcast 'opportunity_won'
history rows = stage history (value, at) — the audit the forecast trusts
```

## Supporting FRs

- FR-CRM-001-001 pipeline config + stage-probability single-source
- FR-CRM-001-002 kanban view (columns, move via buttons v1) + card data
- FR-CRM-001-003 weighted forecast resolver + block (commit tier, by month)
- FR-CRM-001-004 opportunity.pipeline process + won/lost guards + history
- FR-CRM-001-005 lead conversion wiring (converted_date/converted_to written;
  lead_status advanced; contiguous funnel)
- FR-CRM-001-006 stage notices + opportunity_won broadcast
- FR-CRM-001-007 unit/UAT

## Acceptance Criteria (UAT)

1. Unit: forecast resolver matches hand-computed Σ(value × stage_probability)
   on seeded rows; commit tier = rows ≥70%; lost/closed excluded.
2. Unit: won without quote_id/note refused; lost without reason refused;
   probability recomputes from stage (stale stored %, never trust insert).
3. Unit: lead conversion writes converted_date + converted_to_debtor_no,
   advances lead_status 'converted', creates opportunity in first stage.
4. Unit: history rows record value-at-stage; stage-change notify rows written.
5. e2e (live FA container, mirror `e2e_hrm_event_windows.php`): seed 3
   opportunities across stages → kanban renders 3 cards; forecast = weighted
   sum; convert lead → pipeline gains row; won broadcast received by a stub.

## Non-Functional

- PHP 7.3; PSR-4 `CRM\Pipeline\`; FA `db_*` only; `0_` SQL literal;
  probability policy: stage wins by default, per-row override allowed but
  recorded (BR-COM-02 history note).

## Related

- BR-COM-02 (the stage machine), BR-COM-01 (`get_dto` schema for card fields),
  BR-COM-03 (notices), C7 dashboard (reuses forecast resolver), C8 activity
  chaining (won broadcast + lead→opp→quote→order link), C4
  (quote→order after won)
- C1 gap; first CRM BR — establishes the pipeline pattern the other C-BRs
  consume.