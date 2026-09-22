<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Repository;

/**
 * Data access for FR-HRM-007-001 — attendee → employee/external membership.
 *
 * Read contract (FR-HRM-007-001 REQ-1..3 / UC-HRM-007-001 step 2, BON):
 * classification is a SINGLE read-only LEFT JOIN over FA core `0_crm_persons`
 * (email → person) and HRM's own `0_hrm_contacts_employment` (person →
 * employment state). No writes happen during classification, and the
 * EventClosedDto is never touched.
 *
 *   - email resolves to a person with an ACTIVE employment row → membership
 *   - email resolves to a person with NO active employment (contractor /
 *     consultant) → external
 *   - email resolves to NO person → UNCLASSIFIED (never guessed, AZZ)
 *
 * Write contract (FR-HRM-007-001 REQ-4 / UC-HRM-007-001 step 4, CAN):
 * an append-only "worked-window view" is recorded for each member — INSERT
 * only, never UPDATE/DELETE, targeted at HRM's own `0_hrm_event_windows`
 * so the EventClosedDto is never mutated.
 *
 * Fault tolerance (FR-HRM-007-001 REQ-5 / BON): every callable is a thin
 * db_* wrapper; failures are contained so the caller's hook_invoke_all loop
 * keeps going (HRM never throws out of its responders — see hooks.php
 * ksf_event_classify_attendees / ksf_event_closed).
 *
 * @BABOK Related: FR-HRM-007-001, UC-HRM-007-001, BR-HRM-007
 * @since 1.0.0
 */
class EventEmployeeMembershipRepository
{
    use FatRepositoryTrait;

    private const PERSONS_TABLE   = 'crm_persons';
    private const EMPLOYMENT_TABLE = 'hrm_contacts_employment';
    private const WINDOWS_TABLE   = 'hrm_event_windows';

    /**
     * Resolve attendee emails to people + employment state in ONE read.
     *
     * LEFT JOIN keeps a person with no employment row visible (external);
     * emails that are not persons at all are absent from the result set
     * (unclassified).
     *
     * @param string[] $emails Attendee emails (lower-cased by caller)
     * @return array<int,array{person_id:int,email:string,is_active:bool|int|null}>
     */
    public function findPersonsByEmails(array $emails): array
    {
        if (empty($emails)) {
            return array();
        }

        $escaped = array();
        foreach ($emails as $email) {
            $escaped[] = $this->escape(mb_strtolower($email));
        }
        $in = implode(', ', $escaped);

        $sql = "SELECT p.id AS person_id, LOWER(p.email) AS email, e.is_active AS is_active
            FROM " . TB_PREF . self::PERSONS_TABLE . " p
            LEFT JOIN " . TB_PREF . self::EMPLOYMENT_TABLE . " e
                ON e.person_id = p.id
            WHERE LOWER(p.email) IN (" . $in . ")";

        return $this->dbFetchAll($this->dbQuery($sql));
    }

    /**
     * Append an append-only worked-window row (FR-HRM-007-001 REQ-4).
     *
     * INSERT IGNORE + UNIQUE(event_id, person_id) keeps evidence append-only
     * and idempotent for a re-close of the same event. NEVER an UPDATE/DELETE,
     * and never a mutation of the EventClosedDto.
     *
     * @param array $row window fields (event_id, person_id, started_at, closed_at)
     * @return void
     */
    public function insertEventWindow(array $row): void
    {
        $sql = "INSERT IGNORE INTO " . TB_PREF . self::WINDOWS_TABLE . "
            (event_id, person_id, started_at, closed_at)
            VALUES (" .
            $this->intVal($row['event_id']) . ", " .
            $this->intVal($row['person_id']) . ", " .
            $this->escape($row['started_at']) . ", " .
            $this->escape($row['closed_at']) . ")";
        $this->dbQuery($sql);
    }

    /**
     * Count evidence rows (test/audit helper — read-only).
     *
     * @return int
     */
    public function countWindows(): int
    {
        return $this->dbNumRows($this->dbQuery(
            "SELECT window_id FROM " . TB_PREF . self::WINDOWS_TABLE
        ));
    }
}
