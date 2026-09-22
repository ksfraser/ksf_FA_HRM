# BR-CRM-07 — CRM Dashboards & KPIs (C7)

**Modules:** ksf_FA_CRM, substrate BR-COM-01 (`get_dto` schema),
BR-CRM-01 (forecast resolver), BR-CRM-06 (contracts expiring)
**Status:** PENDING (design ratified in this BR)
**Built on:** `pages/dashboard.php`(153) — the 3-counter "Customers /
Opportunities / Leads" block + upcoming follow-ups + recent opportunities
(inline SQL via `CRM_num_*` helpers). gaps doc C7: "3 'leads' matches only."

## Business Need / Current State

The dashboard is real but shallow (counts + two small lists). C1/C6 now
produce real derived data (forecast, contract renewals, pipeline) — C7 makes
the dashboard a true KPI surface using **the same resolvers** (DRY, no
duplicated aggregation), consistent with BR-HRM-06 (HRM dashboard).

## Business Requirement

The business requires:

1. **KPI cards** — top row: open pipeline (count + weighted value from
   BR-CRM-01 forecast), won this-period (count + revenue, from `opportunity_won`
   history), MRR-ish subscriptions count from contracts (BR-CRM-06), open
   tickets (BR-CRM-05), expiring contracts ≤N days).
2. **Chart blocks** — forecast by stage (bar, from C1), expected-close by
   month (C1), pipeline velocity (time-in-stage from BR-CRM-01 history),
   ticket SLA-hit% + reopen rate (C5 resolver), lead conversion rate
   (converted/total, from `0_fa_crm_leads` fields).
3. **Resolvers, not page SQL** — every block is a CalcRegistry resolver
   (C1 forecast, C5 report, C6 renewal, C7 own conversion/velocity) so the
   page assembles widgets; no inline aggregation duplicated across pages.
4. **Owner filters** — a global owner/team filter on the dashboard applies
   to every block (server-side scoping like BR-HRM-08 ESS contract); cards
   show "mine/all".
5. **Deep links** — each card/block links to the source page (pipeline,
   tickets, contracts) — no dead KPI.
6. **Refresh** — computed per request (small data; BR-COM-04 materialization
   cache is optional follow-on).

## Scope

- In scope: widget layout (cards + simple HTML bars no asset lib), the KPI
  resolvers (reuse where they exist; add conversion/velocity), owner filter,
  deep links, unit/UAT.
- Out of scope: charting library, historical trend time-series (a follow-on),
  scheduled snapshot/emailing.

## Design

### Widget contract (resolver registry)

```php
// each block registered: ['key'=>'forecast','title'=>...,'resolver'=>
// 'crm.pipeline.forecast','params'=>['type'=>'sales','as_of'=>now],
// 'render'=>'cards'|'bars'|'table', 'link'=>pages/opportunities.php]
// page loops blocks; CALLS resolver; scopes owner when resolver supports
// $criteria['owner']; renders via FA table/bars; deep-links on click.
```

New resolvers vs reused:
- BR-CRM-01 `crm.pipeline.forecast` → weighted cards + stage bar + by-month
- BR-CRM-05 `crm.tickets.report` → SLA%, open counts, reopen rate block
- BR-CRM-06 `crm.contracts.renewal` → expiring/renewed cards
- C7 own:
  - `crm.leads.conversion(type.ts)` — converted/total, avg time-to-convert
  - `crm.pipeline.velocity(type)` — avg days in each stage from C1 history

### Owner scoping

Every actionable filter passes `criteria['owner']` and the resolvers prepend
`AND assigned_to/assignee_uid = {:owner}` server-side (edit-proof contract,
BR-HRM-08 pattern). Unit-tested: caller-supplied owner overridden by session.

## Supporting FRs

- FR-CRM-007-001 widget registry + page assembler (render registry, links)
- FR-CRM-007-002 KPI cards (pipeline, won, contracts, tickets, expiring)
- FR-CRM-007-003 chart blocks (forecast/stage/by-month/velocity/SLA/
  conversion) via reused or new resolvers
- FR-CRM-007-004 owner/team filter (server-scoped, unit-proof)
- FR-CRM-007-005 deep links everywhere + refresh-on-view
- FR-CRM-007-006 unit/UAT

## Acceptance Criteria (UAT)

1. Unit: dashboard blocks equal the underlying resolvers' outputs on seeded
   data (no page-level recompute drift); forecast card matches C1 weighted.
2. Unit: owner filter returns only my rows in every block; forged owner
   param ignored.
3. Unit: deep links resolve to existing pages; blocks render empty-state
   (no data) without error.
4. e2e (live FA container, `e2e_hrm_event_windows.php` mirror): seed
   opportunities across stages + won, contracts expiring, tickets open →
   dashboard shows matching cards/bars; owner=mine filters; links open.

## Non-Functional

- PHP 7.3; PSR-4 `CRM\Dashboard\`; `db_*` only; `0_` SQL literal;
  widget definitions are data (registry), not hardcoded page sections.
- No charting dependency; simple CSS bars (consistent with BR-HRM-06).

## Related

- BR-CRM-01 (forecast), BR-CRM-05 (tickets), BR-CRM-06 (contracts), BR-HRM-06
  (same widget pattern on HRM side), BR-HRM-08 (owner-scoping contract
  pattern), BR-COM-01 (resolver registry)
- C7 gap; the CRM KPI surface that makes the other C-BR outputs usable at
  a glance.