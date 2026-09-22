<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Service;

use ksfraser\FrontAccounting\HRM\Repository\EventEmployeeMembershipRepository;

/**
 * FR-HRM-007-001 responder — attendee → employee/external membership view.
 *
 * Millisecond-to-millisecond contract with the Timesheets aggregator
 * (BR-007 / FR-TIME-007 / ARCH-007):
 *
 *   caller:  hook_invoke_all('ksf_event_classify_attendees',
 *                $payload, $opts)   // payload = dto + classification (by ref)
 *
 *   HRM:     resolves attendee emails against HRM-OWNED employment state and
 *            APPENDS (by reference) into $data['classification']['member'] /
 *            ['external']. The EventClosedDto instance is NEVER touched: we
 *            read it, we never write it, and we never guess (AZZ — an email
 *            with no person row stays UNCLASSIFIED).
 *
 * Worked-window evidence (REQ-4 / UC-HRM-007-001): on `ksf_event_closed` HRM
 * appends ONE read-only, append-only row per (event, member person) into its
 * OWN `0_hrm_event_windows` table — INSERT-only, never UPDATE/DELETE, never
 * DTO-mutating, idempotent on re-close (see
 * EventEmployeeMembershipRepository::insertEventWindow).
 *
 * Fault tolerance (REQ-5 / BON): every responder wraps the repository in
 * try/catch(Throwable) and swallows — the caller's hook_invoke_all loop keeps
 * going; HRM never throws out of its responders.
 *
 * @BABOK Related: FR-HRM-007-001, UC-HRM-007-001, BR-007
 * @since 1.0.0
 */
class EventEmployeeMembershipService
{
    /** HR-track tokens that make an event a training/work evidence candidate. */
    private const HR_TRACK_TOKENS = array('training', 'category', 'department');

    /** @var EventEmployeeMembershipRepository */
    private $repo;

    public function __construct(?EventEmployeeMembershipRepository $repo = null)
    {
        $this->repo = $repo ?? new EventEmployeeMembershipRepository();
    }

    /**
     * Respond to ksf_event_classify_attendees — append member/external emails.
     *
     * @param array $data Payload (by ref): dto + classification
     * @param array|null $opts hook options (event_id, ...)
     * @return void
     */
    public function classifyAttendees(array &$data, ?array $opts = null): void
    {
        if (!isset($data['dto'], $data['classification']) || !is_array($data['classification'])) {
            return;
        }

        $dto = $data['dto'];
        if (!$this->isHrTrack($dto)) {
            return; // not our track → no opinion
        }

        $emails = $this->attendeeEmails($dto);
        if (empty($emails)) {
            return;
        }

        try {
            $rows = $this->repo->findPersonsByEmails($emails);
        } catch (\Throwable $e) {
            error_log('[ksf_FA_HRM] ksf_event_classify_attendees resolve failed: ' . $e->getMessage());
            return;
        }

        $member   =& $data['classification']['member'];
        $external =& $data['classification']['external'];
        if (!is_array($member)) {
            $member = array();
        }
        if (!is_array($external)) {
            $external = array();
        }

        foreach ($emails as $email) {
            $key = $this->classifyOne($email, $rows);
            if ($key === 'member') {
                $member[] = $email;
            } elseif ($key === 'external') {
                $external[] = $email;
            }
            // 'unclassified' → append nothing, never guessed
        }
    }

    /**
     * Respond to ksf_event_closed — append worked-window evidence (REQ-4).
     *
     * @param object|array $dto EventClosedDto (read-only input)
     * @param array|null $opts Options
     * @return int Number of windows appended
     */
    public function recordWorkedWindows($dto, ?array $opts = null): int
    {
        if (!$this->isHrTrack($dto) || !$this->isClosed($dto)) {
            return 0;
        }

        $emails = $this->attendeeEmails($dto);
        if (empty($emails)) {
            return 0;
        }

        $eventId = $this->dtoEventId($dto);
        $started = $this->dtoDateTime($dto, 'started_at');
        $closed  = $this->dtoDateTime($dto, 'closed_at');

        try {
            $rows = $this->repo->findPersonsByEmails($emails);
            $count = 0;
            foreach ($emails as $email) {
                if ($this->classifyOne($email, $rows) !== 'member') {
                    continue; // only ACTIVE employees get a worked window
                }
                $person = $this->personForEmail($email, $rows);
                if ($person === null) {
                    continue;
                }
                $this->repo->insertEventWindow(array(
                    'event_id'   => $eventId,
                    'person_id'  => (int)$person['person_id'],
                    'started_at' => $started,
                    'closed_at'  => $closed,
                    'track_token' => $this->hrTrackToken($dto),
                ));
                $count++;
            }
            return $count;
        } catch (\Throwable $e) {
            error_log('[ksf_FA_HRM] ksf_event_closed window append failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * True when the dto (object or array) reports an HR-ish track: training /
     * category / department in event_type or linked_entities[].entity_type.
     *
     * @param mixed $dto
     * @return bool
     */
    public function isHrTrack($dto): bool
    {
        $eventType = strtolower((string)$this->dtoValue($dto, 'event_type'));
        if (in_array($eventType, self::HR_TRACK_TOKENS, true)) {
            return true;
        }

        $linked = $this->dtoValue($dto, 'linked_entities');
        if (is_array($linked)) {
            foreach ($linked as $entity) {
                $type = strtolower((string)($entity['entity_type'] ?? $entity['type'] ?? ''));
                if (in_array($type, self::HR_TRACK_TOKENS, true)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * First tracked token used to justify this window's evidence row.
     *
     * @param mixed $dto
     * @return string
     */
    private function hrTrackToken($dto): string
    {
        $eventType = strtolower((string)$this->dtoValue($dto, 'event_type'));
        if (in_array($eventType, self::HR_TRACK_TOKENS, true)) {
            return $eventType;
        }
        $linked = $this->dtoValue($dto, 'linked_entities');
        if (is_array($linked)) {
            foreach ($linked as $entity) {
                $type = strtolower((string)($entity['entity_type'] ?? $entity['type'] ?? ''));
                if (in_array($type, self::HR_TRACK_TOKENS, true)) {
                    return $type;
                }
            }
        }
        return 'unknown';
    }

    /**
     * Classify ONE attendee email against employment rows.
     *
     * @param string $email Lower-cased attendee email
     * @param array $rows Employment rows (email → person)
     * @return string 'member'|'external'|'unclassified'
     */
    private function classifyOne(string $email, array $rows): string
    {
        foreach ($rows as $row) {
            if (strtolower((string)($row['email'] ?? '')) !== $email) {
                continue;
            }
            if ($this->isTruthy($row['is_active'] ?? null)) {
                return 'member';
            }
            return 'external';
        }
        return 'unclassified';
    }

    /**
     * Person row (person_id) for a given attendee email.
     *
     * @param string $email
     * @param array $rows
     * @return array|null
     */
    private function personForEmail(string $email, array $rows): ?array
    {
        foreach ($rows as $row) {
            if (strtolower((string)($row['email'] ?? '')) === $email) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Loose truthy check for db values (1/'1'/true/tint saved via db_escape).
     *
     * @param mixed $value
     * @return bool
     */
    private function isTruthy($value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        return (string)$value === '1'
            || (string)$value === 'true'
            || (string)$value === 'yes';
    }

    /**
     * Whether the dto is closed (carries started_at + closed_at).
     *
     * @param mixed $dto
     * @return bool
     */
    private function isClosed($dto): bool
    {
        $started = $this->dtoValue($dto, 'started_at');
        $closed  = $this->dtoValue($dto, 'closed_at');
        return $started !== null && $started !== '' && $closed !== null && $closed !== '';
    }

    /**
     * Lower-cased, de-duplicated attendee emails from the dto.
     *
     * @param mixed $dto
     * @return string[]
     */
    private function attendeeEmails($dto): array
    {
        $list = $this->dtoValue($dto, 'attendee_emails');
        if (!is_array($list)) {
            $list = array();
        }

        $out = array();
        foreach ($list as $email) {
            $normalized = strtolower(trim((string)$email));
            if ($normalized !== '' && !in_array($normalized, $out, true)) {
                $out[] = $normalized;
            }
        }
        return $out;
    }

    // ─── dto access helpers (object with toArray()/getters OR plain array) ──

    /**
     * Read a key from an array or a DTO (array | toArray() | getX() | get(key)).
     *
     * @param mixed $dto
     * @param string $key
     * @return mixed
     */
    private function dtoValue($dto, string $key)
    {
        if (is_array($dto)) {
            return $dto[$key] ?? null;
        }
        $array = method_exists($dto, 'toArray') ? $dto->toArray() : null;
        if (is_array($array)) {
            return $array[$key] ?? null;
        }
        $getter = 'get' . str_replace('_', '', ucwords($key, '_'));
        if (method_exists($dto, $getter)) {
            return $dto->{$getter}();
        }
        return method_exists($dto, 'get') ? $dto->get($key) : null;
    }

    private function dtoEventId($dto): int
    {
        return (int)$this->dtoValue($dto, 'event_id');
    }

    private function dtoDateTime($dto, string $key): string
    {
        $value = $this->dtoValue($dto, $key);
        if ($value instanceof \DateTimeImmutable) {
            return $value->format('Y-m-d H:i:s');
        }
        return (string)($value ?? '');
    }
}
