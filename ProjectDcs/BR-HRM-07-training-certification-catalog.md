# BR-HRM-07 — Training & Certification Catalog (H8)

**Modules:** ksf_FA_HRM, BR-007 event-window evidence (existing attendee →
worked-window), substrate BR-COM-02 (enrollment flows) / BR-COM-04 (expiry)
**Status:** PENDING (design ratified in this BR)
**Built on:** Only `0_hrm_event_windows` today (attendee→worked-window
attendance evidence, BR-007). **No course catalog, no enrollment, no
certification/expiry tracking.** gaps doc H8 evidence is exactly that.

## Business Need / Current State

The only training artifact in the HRM tree is BR-007's `0_hrm_event_windows`
(proves an attendee was present for a window). OrangeHRM/Odoo both have:
course catalog (content, duration, mode, cost), enrollments (who attended,
when, passed), certifications with **expiry dates** + renewal reminders. H8
adds that catalog over the existing attendance evidence so a completed course
can yield a certificate record.

## Business Requirement

The business requires:

1. **Course catalog** — `(course_id, code, title, category, duration_hours,
   mode (in-person/online), cost)`; active/retired; searchable via
   `get_dto_list` criteria (category/keyword/status).
2. **Enrollment** — per employee per course: enrolled, attended
   (linked from `0_hrm_event_windows` where the course's sessions are events),
   passed/failed, learned date, grade. Enrollment is a BR-COM-02 process
   (`enrolled→attended→passed|failed`; `failed` allows re-enroll).
3. **Certification + expiry** — a passing enrollment can issue a certificate
   record `(person, course, issued_at, expires_at)`; `expires_at` derived by
   data (course-level validity). BR-COM-04 job flags expiring-soon
   (reminder via BR-COM-03); an expired cert `notify`s the employee's manager.
4. **Linked evidence** — attendance rows for a session map to the enrollment
   (the BR-007 window already records attendees; the enrollment references
   it). No duplicate attendance store.
5. **Skill/cost attribution (optional v1)** — per-course cost per person for
   BR-HRM-06 cost report; a `cost` column on enrollment fills from catalog.

## Scope

- In scope: catalog + enrollment + certification tables, enrollment process,
  certificate issue/expiry + BR-COM-04 check, session→enrollment link over
  `0_hrm_event_windows`, reminders, unit/UAT.
- Out of scope: LMS content hosting, online-quiz scoring, external cert
  import, training budget/approval flow (BR-COM-02 could add later).

## Design

### Tables

```sql
CREATE TABLE IF NOT EXISTS `0_hrm_courses` (
  `course_id`   INT(11) NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(40) NOT NULL,
  `title`       VARCHAR(160) NOT NULL,
  `category`    VARCHAR(60) NULL,
  `duration_hours` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `mode`        VARCHAR(12) NOT NULL DEFAULT 'in-person',
  `cost`        DECIMAL(15,2) NOT NULL DEFAULT 0,
  `cert_validity_days` INT(11) NULL,           -- NULL = certificate never expires
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`course_id`),
  UNIQUE KEY `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `0_hrm_enrollments` (
  `enrollment_id` INT(11) NOT NULL AUTO_INCREMENT,
  `course_id`  INT(11) NOT NULL,
  `person_id`  INT(11) NOT NULL,
  `state`      VARCHAR(16) NOT NULL DEFAULT 'enrolled', -- BR-COM-02 facing
  `window_id`  INT(11) NULL,                   -- 0_hrm_event_windows attendance
  `grade`      VARCHAR(12) NULL,
  `cost`       DECIMAL(15,2) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`enrollment_id`),
  KEY `idx_person_state` (`person_id`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `0_hrm_certificates` (
  `cert_id`    INT(11) NOT NULL AUTO_INCREMENT,
  `person_id`  INT(11) NOT NULL,
  `course_id`  INT(11) NOT NULL,
  `issued_at`  DATE NOT NULL,
  `expires_at` DATE NULL,
  `ref_enrollment_id` INT(11) NOT NULL,
  PRIMARY KEY (`cert_id`),
  KEY `idx_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Enrollment process (BR-COM-02, small definition)

```
states: enrolled -> attended -> passed|failed   (failed may re-enroll)
start:  'enrolled' on enrollment create
auto:   enrolled->attended when window attendance recorded for that person
        (BR-007 windows being the session attendance; listener sets attended)
user:   attended->passed OR attended->failed (grade + note)
post:   [passed] -> [call 'hrm.certificates.issue'] with expires_at = 
                    issued + cert_validity_days (NULL = never expires)
        [failed] -> [call 'notify'] manager; re-enroll offered in page
```

Note: certificate issue is a resolver that may optionally `broadcast`
`cert_issued` for H8 portal visibility.

### Expiry / expiry-soon (BR-COM-04)

Recurring job `hrm.certificates.check`: 30 days before `expires_at` → notify
owner+manager (reminder); on/after → mark expired (view query, no row
mutation) + notify manager. Certification status shown on employee page +
H8 portal.

## Supporting FRs

- FR-HRM-007-001 course catalog (CRUD via get_dto_list + search)
- FR-HRM-007-002 enrollment process (enrolled→attended→passed|failed, re-enroll)
- FR-HRM-007-003 session→enrollment link over `0_hrm_event_windows` (BR-007)
- FR-HRM-007-004 certificate issue/expiry + BR-COM-04 check job + reminders
- FR-HRM-007-005 unit/UAT

## Acceptance Criteria (UAT)

1. Unit: enrollment lifecycle transitions correct; `failed` re-enroll creates
   a fresh enrollment; `passed` issues certificate with expiry = issued +
   validity (NULL never expires).
2. Unit: attended auto-fires when BR-007 window attendance exists for person
   + course session (listener/link).
3. Unit: expiry-check job finds certificates <30d → reminder rows (BR-COM-03)
   for owner+manager; at-expiry flags manager notice.
4. e2e (live FA container, mirror `e2e_hrm_event_windows.php`): seed course +
   cert with near expiry, write BR-007 window for enrollment → assert
   attended, passed, certificate issued, reminder rows appear.

## Non-Functional

- PHP 7.3; PSR-4 `HRM\Training\`; FA `db_*` only; `0_` SQL literal;
  attendance continues to live only in BR-007 windows (no duplicate store).

## Related

- BR-007 (attendance evidence), BR-COM-02/04 (flows + expiry job), BR-COM-03
  (reminders), BR-HRM-06 (training cost in reports)
- H8 gap; feeds H9 (cert record as managed artifact) and H8 portal (my
  certificates).