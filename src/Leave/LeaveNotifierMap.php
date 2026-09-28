<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Leave;

/**
 * FA runtime person -> FA user id mapping (BR-HRM-01 FR-HRM-001-008).
 *
 * Inbox rows are addressed to FA users (0_users.id, numeric). Leave dto
 * carries HR person ids (0_crm_persons.id). Employment links the two:
 *   0_hrm_contacts_employment.login_id  = 0_users.user_id (login string)
 *     (one row per person, UNI key on person_id)
 *   0_users.user_id -> 0_users.id (numeric pk) = FA recipient uid.
 * Native db_* calls only (FA runtime HARD RULE). Rootless CLI/tests inject
 * their own callable, never this map.
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 */
final class LeaveNotifierMap
{
    /**
     * Resolve an HR person id to their FA user id.
     *
     * @return int|null null when the person has no employment/login row
     */
    public static function faUserIdForPerson(int $personId): ?int
    {
        if ($personId <= 0) {
            return null;
        }

        $sql = "SELECT u.id AS user_id
            FROM " . TB_PREF . "hrm_contacts_employment e
            INNER JOIN " . TB_PREF . "users u ON u.user_id = e.login_id
            WHERE e.person_id = " . (int) $personId . " LIMIT 1";
        $result = db_query($sql);
        if (!$result || !db_num_rows($result)) {
            return null;
        }
        $row = db_fetch_assoc($result);
        return (int) $row['user_id'];
    }
}