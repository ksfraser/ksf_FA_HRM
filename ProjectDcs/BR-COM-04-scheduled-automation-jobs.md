# BR-COM-04 — Scheduled Automation Jobs (cron-friendly CLI runner)

**Modules:** ksf_FA_Common (engine + CLI + registry), consuming modules
(HRM payroll/leave accrual, CRM quote expiry/campaign sends, BR-COM-02
time-wait edges, BR-COM-03 digest)
**Status:** PENDING (design ratified in this BR)
**Built on:** BR-COM-01 (CalcRegistry resolvers), BR-COM-02 (time-wait
transitions), BR-COM-03 (digest jobs), BR-007 (append-only discipline)

## Business Need

FA is a web application with no module-friendly scheduled worker. Recurring
work — leave-balance accrual, payroll batch runs, quote expiry, campaign sends,
BR-COM-02 time-gated transitions (e.g. "auto-approve if no response in 3 days"),
BR-COM-03 digests — currently must be triggered by a human clicking a page, or
is simply not done. Odoo (scheduled actions), SuiteCRM (cron jobs), and
Dolibarr (background tasks) all ship a scheduler; the gaps doc flags S4 for us.

The design must stay FA-native: no new daemon, no long-running process, no
hard PHP version bump. A **cron-fired CLI entry** (FA already runs under
PHP 7.3/7.4) that bootstraps FA's own `db_connect()`/`db_*` layer, reads a
**jobs registry**, and runs whatever is due — is the whole mechanic.

## Business Requirement

The business requires:

1. **Jobs registry** — a table of named jobs, `(module, key, interval,
   type)`; each job points at a **CalcRegistry resolver** (BR-COM-01) as its
   runnable body, so a job is authorable-as-data like workflow steps, reusing
   the exact same resolver/lifecycle machinery — no parallel task framework.
2. **Due-check + run** — a CLI entry reads the registry, evaluates each job
   against its last-run timestamp + interval, and runs due ones; a web-tickle
   fallback runs the same evaluate loop on a page load when cron isn't
   available (so UAT/Integration still exercise it).
3. **Time-gated state transitions** — BR-COM-02's `wait` edges register a
   one-shot job (or recurring clock-pulse job) that fires the transition when
   a deadline passes. Scheduler, not the state machine, owns waking up.
4. **BR-COM-03 digest** — a recurring job walks the notifications table and
   emits one digest row per user per period (v1 digest = mark-oldest-unread
   and produce a summary payload for the inbox/email hook).
5. **Failure handling** — a job that throws is recorded (last_error, next_run
   pushed by backoff), never silently swallowed; a stuck run is recoverable
   via lock cleanup. Append-only `run_log` mirrors BR-007/BR-COM-02.
6. **Locking** — cron-overlap protection: two workers must not run the same
   job concurrently. FA-native: DB advisory lock via `GET_LOCK()`/`RELEASE_
   LOCK()` (mysqli) — no pid files to go stale.
7. **Idempotent bodies** — the resolver contract REQUIRES a job body to be
   idempotent (running it twice = running it once, by construction), because
   cron delivery is at-least-once. This is enforced at review/UT, not by the
   engine.
8. **Injectable / testable** — the evaluate loop takes the registry + clock
   as inputs (DI), so unit tests drive "run anything due at
   2026-09-22 03:00" with a fake clock and a stub resolver — no cron, no DB.
   The FA adapter integrates the real table / `db_*` under the interface.

## Scope

- In scope: jobs registry table + access adapter, CLI entry, evaluate loop
  (registry + clock injected), `GET_LOCK()` overlap guard, run_log, one-shot
  + recurring job types, time-wait wiring for BR-COM-02, web-tickle fallback.
- Out of scope: supervisor/daemon, distributed queues, retry DLQs, email
  sending itself (BR-COM-03 hooks the mail module), job DSL beyond key +
  interval + type.

## Design

### Registry table (append-only log + mutable job row; `0_` literal)

```sql
CREATE TABLE IF NOT EXISTS `0_ksf_wf_jobs` (
  `job_id`     INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `module`     VARCHAR(60) NOT NULL,          -- owning module (schema owner)
  `job_key`    VARCHAR(120) NOT NULL,         -- 'hrm.leave_accrual.monthly'
  `job_type`   VARCHAR(10) NOT NULL,          -- 'recurring' | 'oneshot'
  `interval_p` VARCHAR(20) NOT NULL,          -- '1 month', '1 day', '1 hour', '3 days'
  `resolver`   VARCHAR(120) NOT NULL,         -- CalcRegistry key, BR-COM-01
  `params`     TEXT NULL,                     -- JSON payload for the resolver
  `enabled`    TINYINT(1) NOT NULL DEFAULT 1,
  `last_run_at` DATETIME NULL,
  `next_run_at` DATETIME NULL,                -- oneshot: explicit fire time
  `last_error`  TEXT NULL,
  `created_at`  DATETIME NOT NULL,
  PRIMARY KEY (`job_id`),
  KEY `idx_next_run` (`next_run_at`, `enabled`)
);

CREATE TABLE IF NOT EXISTS `0_ksf_wf_run_log` (  -- append-only
  `run_id`    INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id`    INT(11) NOT NULL,
  `started_at` DATETIME NOT NULL,
  `finished_at` DATETIME NULL,
  `status`    VARCHAR(12) NOT NULL,           -- 'ok' | 'failed' | 'locked' | 'skipped'
  `detail`    TEXT NULL,
  PRIMARY KEY (`run_id`),
  KEY `idx_job_status` (`job_id`, `status`)
);
```

### Evaluate loop (the whole engine)

```php
// calculator: (jobs, clock, logger) -> void — DI-friendly core
$engine = new JobRunner($jobsAdapter, $clock, $logger);
$engine->runDue(new DateTimeImmutable('2026-09-22 03:00'));

// FA-native integration: same loop, real table + db_* + GET_LOCK()
$engine = new JobRunner(new FaJobsAdapter(), new SystemClock(), new FaLogger());
$engine->runDue(new SystemClock()->now());
// assert(GET_LOCK('ksf_wf_jobs', 0) === 1) else status='locked', skip
// each taken job: 'running' -> resolver($params) -> 'ok'/backoff on throw
```

### Loop rules

1. **Due** = `next_run_at <= now` (recurring recomputes next); **oneshot**
   sets `next_run_at = fire-time` and `enabled = 0` on success.
2. **Per job**: `GET_LOCK('ksf_wf_job:<id>', 0)`; acquire → run → log
   append-only → release. No acquired → `run_log` 'locked' row, continue.
3. **Throw**: `run_log` 'failed' + `last_error`, `next_run_at` = now + backoff
   (min(interval, 5 attempts then disabled)); never re-raises into the page.
4. **Clock injection** used by UT; SystemClock() only in the FA adapter.
5. **Web-tickle fallback**: an FA page (or existing dashboard) calls the same
   `runDue(now)` but caps at N due jobs + a `ksf-wf:lock` mutex to survive
   concurrent page hits; logs 'web' source. This covers non-cron hosts and
   lets UAT/Integration actually run accrual/expiry without a crontab.

### Time-wait wire to BR-COM-02

A transition with `wait` resolves its `next_run_at` at fire time and registers
a **oneshot job** keyed `wf:trans:<recordType>:<instanceId>:<from>:<to>`;
the due job's resolver loads the state row and calls
`engine->transition(dto, to, 'system', 'deadline')` — the scheduler wakes the
state machine, never the reverse (BR-COM-02 lease-to-scheduler rule). The
oneshot is idempotent: if the instance already left `from`, it becomes a
no-op 'skipped' job.

## Supporting FRs

- FR-COM-04-001 jobs registry + adapter (`FaJobsAdapter` / in-memory for UT)
- FR-COM-04-002 evaluate loop (due-check, clock injection, run_log)
- FR-COM-04-003 GET_LOCK overlap guard + 'locked'/'failed'/'ok' run rows
- FR-COM-04-004 recurring + oneshot types, backoff, auto-disable after 5
- FR-COM-04-005 CLI entry (`cron.php` bootstrapping FA + db_connect)
- FR-COM-04-006 web-tickle fallback (capped + mutexed) for UAT boxes
- FR-COM-04-007 time-wait one-shot wire to BR-COM-02 transitions
- FR-COM-04-008 BR-COM-03 digest job (recurring, per-user summary)
- FR-COM-04-009 e2e: a recurring job runs on the live FA DB, run rows
  appear, second call within interval adds no run (idempotence + lock)

## Acceptance Criteria (UAT)

1. Unit: fake clock + stub resolver — "run due at 03:00" runs exactly the
   jobs whose `next_run_at <= 03:00`, writes ok rows, recomputes next.
2. Unit (lock): holding `GET_LOCK` for job X causes a concurrent run to log
   'locked' and skip X (no double execution) — proves overlap safety.
3. Unit (failure): a throwing resolver → 'failed' row + last_error +
   backoff next_run; after 5 consecutive, job disabled.
4. e2e (live FA container, mirrors `e2e_hrm_event_windows.php`): seed an
   HRM leave-accrual job due now → run CLI → status ok + accrual rows; run
   again immediately → no new accrual rows (idempotent).
5. e2e time-wait: a BR-COM-02 edge with `wait 3 days` registers a one-shot;
   when its fire time passes and `from` is unchanged, the scheduler executes
   the transition; if `from` already changed, job logs 'skipped'.
6. Web tickle: hitting the fallback page with 2 due jobs runs ≤2, logs
   source 'web', and concurrent hits don't double-run (mutex).

## Non-Functional

- PHP 7.3 floor; PSR-4 `Common\Scheduler\`; DB access via FA `db_*` only at
  runtime (HARD RULE — the adapter, not PDO, in FA).
- No new daemon; CLI + web-tickle only. Cron line is one entry:
  `* * * * * php <path>/modules/ksf_FA_Common/cron.php >/dev/null`.
- At-least-once semantics documented per job; bodies REQUIRED idempotent.

## Related

- BR-COM-02 (time-wait edges leased to scheduler; wake-on-deadline)
- BR-COM-03 (digest job; notification routing)
- BR-COM-01 (CalcRegistry resolvers as the reusable job bodies)
- S4 gap; feeds H2 payroll run, H3 leave accrual, C4 quote expiry
- AGENTS.md §FA-extension mechanics (SQL uses literal `0_`, verified vs
  `db_import()` behavior)