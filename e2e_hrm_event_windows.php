<?php

/**
 * Integration/e2e: FR-HRM-007-001 worked-window evidence against the live FA DB.
 *
 * Runs INSIDE the FA container (php7.4) against mod://fa_modules -> /var/www/html/modules.
 * Real flow: module SQL DDL -> repository SELECT (classification) -> INSERT IGNORE
 * evidence -> assert row. Uses FA-shaped db_* wrappers over the live mysqli handle,
 * the same calls FatRepositoryTrait makes (db_query/db_fetch_assoc/db_escape/...).
 *
 * Usage:   php e2e_hrm_event_windows.php
 * Exit:    0 = green, 1 = failed (asserts), 2 = boot failure
 *
 * @since 1.0.0
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// ── 1. boot live FA connection ─────────────────────────────────────────────
$root = __DIR__;                       // .../modules/ksf_FA_HRM (module root)
include '/var/www/html/config_db.php';
if (!isset($db_connections[0])) {
    fwrite(STDERR, "BOOT FAIL: no db_connections\n");
    exit(2);
}
$c = $db_connections[0];
$DB = new mysqli($c['host'], $c['dbuser'], $c['dbpassword'], $c['dbname'], $c['port'] ?? 3306);
if ($DB->connect_errno) {
    fwrite(STDERR, "CONNECT FAIL: " . $DB->connect_error . "\n");
    exit(2);
}
echo "connected to {$c['name']} ({$c['dbname']}) \n";

// FA connect_db_mysqli.inc sets an empty sql_mode on every new connection
$DB->query("SET sql_mode = ''");

if (!defined('TB_PREF')) {
    define('TB_PREF', $c['tbpref'] ?? '0_');
}

// FA-shaped procedural wrappers over the live handle (mirror connect_db_mysqli.inc)
function db_query($sql, $err_msg = null)
{
    global $DB;
    $r = $DB->query($sql);
    if ($r === false) {
        throw new RuntimeException('db_query: ' . $DB->error . ' SQL: ' . $sql);
    }
    return $r;
}
function db_fetch_assoc($result)
{
    return $result ? $result->fetch_assoc() : false;
}
function db_escape($value)
{
    global $DB;
    if ($value === null) {
        return 'NULL';
    }
    return "'" . $DB->real_escape_string((string)$value) . "'";
}
function db_insert_id()
{
    global $DB;
    return (int)$DB->insert_id;
}
function db_num_rows($result)
{
    return $result ? (int)$result->num_rows : 0;
}

// ── 2. apply the real module DDL (literal 0_ prefix per FA convention) ─────
$ddl = file_get_contents($root . '/sql/ksf_hrm_event_windows.sql');
if ($ddl === false) {
    fwrite(STDERR, "DDL READ FAIL\n");
    exit(2);
}
db_query($ddl);
echo "table 0_hrm_event_windows ensured\n";

$failures = 0;

// ── 3. seed a live person + ACTIVE employment row ──────────────────────────
$pEmail = 'e2e.alice@example.com';
$pEmailEsc = db_escape($pEmail);
$pName = 'E2E HRM Alice';

// cleanup previous runs (test-only person, isolated email)
db_query("DELETE FROM " . TB_PREF . "crm_persons WHERE email = $pEmailEsc");

$insertPerson = "INSERT INTO " . TB_PREF . "crm_persons (ref, name, email, inactive)
    VALUES (" . db_escape('E2E-P') . ", " . db_escape($pName) . ", $pEmailEsc, 0)";
db_query($insertPerson);
$personId = db_insert_id();

db_query("DELETE FROM " . TB_PREF . "hrm_contacts_employment WHERE person_id = $personId");
$insertEmployment = "INSERT INTO " . TB_PREF . "hrm_contacts_employment
    (person_id, employee_code, is_active)
    VALUES ($personId, " . db_escape('E2E-EMP-1') . ", 1)";
db_query($insertEmployment);

// also a contractor (no employment row) for the "external" bucket
$cEmail = 'e2e.bob@consultco.com';
$cEmailEsc = db_escape($cEmail);
db_query("DELETE FROM " . TB_PREF . "crm_persons WHERE email = $cEmailEsc");
db_query("INSERT INTO " . TB_PREF . "crm_persons (ref, name, email, inactive)
    VALUES (" . db_escape('E2E-C') . ", " . db_escape('E2E HRM Bob') . ", $cEmailEsc, 0)");

echo "seeded person_id=$personId alice + contractor\n";

// ── 4. drive the deployed service exactly as hook responders do ────────────
require_once $root . '/vendor/autoload.php';

$service = new \ksfraser\FrontAccounting\HRM\Service\EventEmployeeMembershipService();

// 4a. classification (ksf_event_classify_attendees responder body)
$payload = array(
    'dto'            => array(
        'event_id'        => 9001,
        'event_type'      => 'training',
        'linked_entities' => array(array('entity_type' => 'training')),
        'attendee_emails' => array($pEmail, $cEmail, 'e2e.ghost@example.com'),
        'started_at'      => '2026-09-22 09:00:00',
        'closed_at'       => '2026-09-22 17:00:00',
    ),
    'classification' => array('member' => array(), 'external' => array()),
);
$service->classifyAttendees($payload);

$member   = $payload['classification']['member']   ?? array();
$external = $payload['classification']['external'] ?? array();

if (in_array($pEmail, $member, true) && !in_array($cEmail, $member, true)) {
    echo "PASS: active member classified (member=$pEmail)\n";
} else {
    echo "FAIL: member bucket wrong — " . json_encode($payload['classification']) . "\n";
    $failures++;
}
if (in_array($cEmail, $external, true)) {
    echo "PASS: contractor classified external ($cEmail)\n";
} else {
    echo "FAIL: contractor missing from external bucket — " . json_encode($payload['classification']) . "\n";
    $failures++;
}
if (!in_array('e2e.ghost@example.com', array_merge($member, $external), true)) {
    echo "PASS: unknown email never guessed (AZZ)\n";
} else {
    echo "FAIL: unknown email was guessed into a bucket\n";
    $failures++;
}

// 4b. worked-window evidence (ksf_event_closed responder body)
$win = $service->recordWorkedWindows($payload['dto']);
if ($win === 1) {
    echo "PASS: one worked-window appended (member only)\n";
} else {
    echo "FAIL: expected 1 window, got $win\n";
    $failures++;
}

// ── 5. assert the evidence row landed in the live table ────────────────────
$row = db_fetch_assoc(db_query(
    "SELECT event_id, person_id, started_at, closed_at
     FROM " . TB_PREF . "hrm_event_windows
     WHERE event_id = 9001 AND person_id = $personId"
));
if ($row !== false && (int)$row['person_id'] === (int)$personId) {
    echo "PASS: evidence row in live 0_hrm_event_windows (event={$row['event_id']}, person={$row['person_id']})\n";
} else {
    echo "FAIL: no evidence row for event 9001 / person $personId — " . json_encode($row) . "\n";
    $failures++;
}

// idempotency: re-close the same event → still exactly one row
$service->recordWorkedWindows($payload['dto']);
$cnt = db_fetch_assoc(db_query(
    "SELECT COUNT(*) AS c FROM " . TB_PREF . "hrm_event_windows WHERE event_id = 9001"
));
if ((int)($cnt['c'] ?? 0) === 1) {
    echo "PASS: re-close is idempotent (INSERT IGNORE + UNIQUE)\n";
} else {
    echo "FAIL: re-close produced " . (int)($cnt['c'] ?? 0) . " rows, expected 1\n";
    $failures++;
}

// ── 6. cleanup test-only rows ──────────────────────────────────────────────
db_query("DELETE FROM " . TB_PREF . "hrm_event_windows WHERE event_id = 9001");
db_query("DELETE FROM " . TB_PREF . "hrm_contacts_employment WHERE person_id = $personId");
db_query("DELETE FROM " . TB_PREF . "crm_persons WHERE email = $pEmailEsc OR email = $cEmailEsc");
echo "cleaned up test rows\n";

if ($failures > 0) {
    fwrite(STDERR, "E2E FAILED ($failures)\n");
    exit(1);
}
echo "E2E GREEN\n";
exit(0);