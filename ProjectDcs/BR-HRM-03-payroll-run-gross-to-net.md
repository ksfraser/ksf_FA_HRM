# BR-HRM-03 — Payroll Run: Gross → Net (H2)

**Modules:** ksf_FA_HRM, FA core GL posting (audit trail), substrate from
ksf_FA_Common (BR-COM-04 batch job)
**Status:** PENDING (design ratified in this BR)
**Built on:** BR-COM-04 (scheduled batch runs), BR-HRM-01 (leave/absence
inputs), salary structure (`fa_salary_structure` position×grade×element) and
pay elements (`fa_pay_elements` BASIC/HRA/TAX/SS/HI/RF) — both exist today.

## Business Need / Current State (verbatim from tree)

`PayrollService` is **CRUD-only** (`listAll/getById/getHistory/create/update/
delete/getEntries/addEntry`); `pages/payroll.php` is a **read-only list**
(gross/deductions/net/status, `status='Draft'`). Existing data:

- `ksf_hrm_payroll` (gross_pay, total_deductions, net_pay, pay_date, status
  Draft, gl_posted) + `ksf_hrm_payroll_entries` (element × amount).
- `fa_pay_elements` seeds Basic/HRA/TA/MA/Bonus/OT (earnings, `is_taxable`,
  `affects_gross`) and TAX/SS/HI/RF (deductions). `fa_salary_structure`
  maps position×grade×element→pay_amount/formula, with effective ranges.

**Nothing computes.** No gross→net run, no element-priority/order, no tax or
social calc, no payslip, no batch, no GL posting, no reversals. The gaps doc
flags H2 High ("gross→net run, tax/social deductions, payslip, batch, GL
posting").

## Business Requirement

The business requires:

1. **Runnable batch** — a pay batch (period, run-date, population) owns a set
   of employee payroll rows; "Run" computes every row gross→net in one
   transaction. Batch is a first-class object (`ksf_hrm_pay_batches`), so
   History/Status exist at batch level, not just per employee (BR-HRM-03
   fixes the missing batch dimension).
2. **Deterministic element math** — the engine walks `fa_pay_elements` by
   `display_order`: `affects_gross` elements sum to gross; `is_taxable`
   tagged amount feeds the tax engine; deductions (TAX/SS/HI/RF) run after
   earnings; net = gross − deductions. Formula-elements resolve from
   `fa_salary_structure.formula`. No hand-rolled per-employee arithmetic.
3. **Configurable tax/social engine** — a resolver (Calculator registry,
   BR-COM-01) per jurisdiction computes income tax + social contributions
   from taxable gross + brackets (config rows, not code). v1 ships a flat +
   progressive-bracket reference implementation; statutory tables are data.
4. **Payslip artifact** — per employee, a printable payslip (gross, elements,
   deductions, tax, social, net, pay-date) rendered from the payroll rows; no
   new artifact system (reuses FA document/print path; the artifact is
   generated on print, not stored — a `payslip` view of the run).
5. **GL posting** — FA `gl`/`audit_trail` entries per batch via FA's native
   posting (payroll expense / deductions payable / net cash). `gl_posted`
   flips once, **non-reversible by UI**; reversal is a new offsetting batch
   (FA audit discipline, mirroring BR-007 append-only).
6. **Scheduled** — BR-COM-04 recurring job `hrm.payroll.run` per pay cadence
   runs due batches (those whose period end passed and status still Draft);
   batch locks via the BR-COM-04 job lock + `gl_posted` guard.
7. **Failure handling** — a row that fails (e.g. missing salary structure)
   is marked `error` with message in the run; the rest of the batch completes;
   the batch can be re-run (idempotent: rows already `paid`/`posted` are
   skipped — at-least-once safe per BR-COM-04 req 7).
8. **Leave/absence inputs** — unpaid leave days (BR-HRM-01) prorate earnings
   via a resolver input (days worked from `0_hrm_leave_request_days`
   consumed/unpaid marker), wired as an element formula feed.

## Scope

- In scope: pay-batch table/flow, element-math engine (order, formula,
  gross/net), tax/social config + reference engine, run + error handling,
  payslip view, GL posting via FA audit trail, BR-COM-04 schedule hook,
  unit/UAT.
- Out of scope: payslips by email (BR-COM-03 mail later), statutory
  verification against real tax authorities (config is data), bank file
  feed, full payroll reports (BR-HRM-06), mid-month corrections beyond a
  reversing batch.

## Design

### Tables (migration, `0_` literal)

```sql
CREATE TABLE IF NOT EXISTS `0_hrm_pay_batches` (
  `batch_id`    INT(11) NOT NULL AUTO_INCREMENT,
  `period_start` DATE NOT NULL,
  `period_end`  DATE NOT NULL,
  `pay_date`    DATE NOT NULL,
  `status`      VARCHAR(12) NOT NULL DEFAULT 'Draft', -- Draft|Running|Paid|Posted|Error
  `gl_posted`   TINYINT(1) NOT NULL DEFAULT 0,
  `created_by`  INT(11) NULL,
  `created_at`  DATETIME NOT NULL,
  `posted_at`   DATETIME NULL,
  PRIMARY KEY (`batch_id`),
  KEY `idx_period` (`period_start`, `period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `0_hrm_pay_tax_config` (  -- config is data
  `config_id`   INT(11) NOT NULL AUTO_INCREMENT,
  `jurisdiction` VARCHAR(40) NOT NULL,
  `kind`        VARCHAR(12) NOT NULL,          -- 'income_tax'|'social'
  `bracket_low` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `bracket_high` DECIMAL(15,2) NULL,
  `rate`        DECIMAL(10,6) NOT NULL,
  `effective_from` DATE NOT NULL,
  `effective_to` DATE NULL,
  PRIMARY KEY (`config_id`),
  KEY `idx_jur_eff` (`jurisdiction`, `effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

(Employee rows stay in `ksf_hrm_payroll`; add `batch_id`,
`tax_amount`, `error_msg`.)

### Compute engine (deterministic, table-driven)

```php
// per employee row: elements ordered by display_order
$gross = compute_gross($elements);                 // affects_gross earners
$taxable = compute_taxable($gross, $elements);     // is_taxable subset
$tax = (new IncomeTaxEngine($config))->compute($taxable);   // brackets = data
$social = (new SocialEngine($config))->compute($taxable);
$deductions = compute_deductions($elements) + $tax + $social; // order-driven
$net = round($gross - $deductions, 2);
// persist entries? re-record computed TAX/SOCIAL as entries with notes.
```

- Idempotent re-run: a row already `Paid` (this batch) is skipped. A batch
  re-run recomputes only `Draft`/`Error` rows (BR-COM-04 at-least-once).
- Transaction: whole batch computes+persists in one FA transaction
  (`begin_transaction/commit`; rollback on batch-level failure); per-row
  errors are captured, not aborting.

### Batch run column (the UI/flow)

1. Human or BR-COM-04 job creates a Draft batch (component population =
   salary-structure-holding employees, effective now).
2. "Run" → engine per above → per-employee rows `Paid` (or `Error`+msg);
   batch becomes `Running`→`Paid` (or `Error` with the row errors listed).
3. "Post" → FA `gl`/`audit_trail` entries for the batch; `gl_posted=1`,
   `posted_at`, status `Posted`. Reversal = new Draft batch with negative
   amounts + a `reverse_of` ref — never a delete.
4. Payslip view: printable per employee from the run rows.

## Supporting FRs

- FR-HRM-003-001 pay-batch table/flow + component population
- FR-HRM-003-002 element-math engine (display_order, formula, gross/net)
- FR-HRM-003-003 tax/social config + reference bracket engine
- FR-HRM-003-004 run + per-row error capture + idempotent re-run
- FR-HRM-003-005 GL posting via FA audit_trail + non-reversible `gl_posted`
- FR-HRM-003-006 payslip view/print
- FR-HRM-003-007 BR-COM-04 recurring job for due batches
- FR-HRM-003-008 leave-day proration input (BR-HRM-01)
- FR-HRM-003-009 unit/UAT

## Acceptance Criteria (UAT)

1. Unit: baseline employee gross/net computed from salary-structure elements;
   TAX+SS from config brackets; net = gross − all deductions.
2. Unit: missing salary structure → row `Error` with message, other rows
   complete; re-run after fixing recomputes only Draft/Error rows.
3. Unit: post flips `gl_posted`, writes FA audit_trail entries, is refused
   when already posted (no double post); reversal creates offsetting batch.
4. Unit: BR-COM-04 due-check runs an unposted Draft batch once; second run
   within interval no-ops.
5. e2e (live FA container, mirrors `e2e_hrm_event_windows.php`): seed
   elements + structure + person → create batch → run → assert Paid rows with
   correct net; post → assert audit_trail + gl_posted; payslip renders.

## Non-Functional

- PHP 7.3 floor; PSR-4 `HRM\Payroll\`; FA `db_*` only; `0_` SQL literal;
  reference tax engine is data-driven (brackets), never hardcoded rates in
  code.
- Single-codebase audit: every post has an FA audit_trail row (no orphan GL).
- Money as DECIMAL, rounded to 2dp once at net (FA convention).

## Related

- BR-COM-04 (scheduler/locks for batch runs), BR-HRM-01 (leave-day proration),
  BR-HRM-06 (payroll summary report), BR-HRM-07 (payroll→training cost
  attribution optional)
- H2 gap; feeds H5 (timesheet→payroll), H9 (payslip as contract artifact).