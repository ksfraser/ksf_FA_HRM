<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Leave;

use ksfraser\FrontAccounting\Common\Notification\Inbox\Notifier;
use ksfraser\FrontAccounting\Common\Notification\Inbox\RecipientResolver;

/**
 * Leave notifications — BR-COM-03 wiring for the leave process
 * (BR-HRM-01 FR-HRM-001-008).
 *
 * Leave dto carries HR person ids, inbox rows carry FA user ids, so every
 * notify resolves person -> FA user first (injected map: FA runtime walks
 * hrm_contacts_employment.login_id -> 0_users.user_id -> 0_users.id; tests
 * stub a person->uid array). Approvers already known as users route via
 * ['users']; SA_LEAVE_APPROVE delegation routes via ['roles'] (no map needed).
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 */
class LeaveNotifier
{
    /** @var callable|null fn(int $personId): ?int */
    private $personToUser;

    public function __construct(?callable $personToUser = null)
    {
        $this->personToUser = $personToUser;
    }

    /**
     * Resolve an HR person id to a FA user id.
     *
     * @return int|null null when the person has no login
     */
    public function userId(int $personId): ?int
    {
        if ($this->personToUser === null) {
            return null;
        }
        $uid = ($this->personToUser)($personId);
        return $uid !== null && (int) $uid > 0 ? (int) $uid : null;
    }

    /**
     * Build a Common Notifier wired to FA role/user lookup (runtime) or an
     * injected in-memory notifier (tests).
     */
    public function notifier(?Notifier $base = null): Notifier
    {
        if ($base !== null) {
            return $base;
        }
        // FaInboxStore + role-backed RecipientResolver: roles work out of the
        // box; explicit user ids come from userId() + ['users'] targets.
        return new Notifier(
            new \ksfraser\FrontAccounting\Common\Notification\Inbox\FaInboxStore(),
            new RecipientResolver()
        );
    }

    /**
     * Notify a person (mapped to their FA user) of an outcome/state change.
     *
     * @param Notifier $notifier
     * @param int      $personId  HR person id (requester or approver)
     * @param string   $type      registered notification type
     * @param array    $dto       leave request dto (payload + ref source)
     *
     * @return int rows stored (0 when person has no login)
     */
    public function notifyPerson(Notifier $notifier, int $personId, string $type, array $dto): int
    {
        $uid = $this->userId($personId);
        if ($uid === null) {
            return 0;
        }
        return $notifier->notify(
            ['users' => [$uid]],
            $type,
            $this->payload($dto),
            $this->ref($dto),
            ['dto' => $dto]
        );
    }

    /**
     * Notify the SA_HOLD_APPROVE role holders (delegation, BR-01 req 5).
     *
     * @return int rows stored
     */
    public function notifyApproveRole(Notifier $notifier, string $type, array $dto): int
    {
        return $notifier->notify(
            ['roles' => ['SA_LEAVE_APPROVE']],
            $type,
            $this->payload($dto),
            $this->ref($dto),
            ['dto' => $dto]
        );
    }

    private function ref(array $dto): string
    {
        return 'hr:leave_request:' . (int) ($dto['request_id'] ?? 0);
    }

    private function payload(array $dto): array
    {
        return [
            'request_id' => (int) ($dto['request_id'] ?? 0),
            'person_id'  => (int) ($dto['person_id'] ?? 0),
            'from_date'  => (string) ($dto['from_date'] ?? ''),
            'to_date'    => (string) ($dto['to_date'] ?? ''),
            'days'       => (float) ($dto['days'] ?? 0),
            'state'      => (string) ($dto['state'] ?? ''),
            'current_approver' => isset($dto['current_approver']) ? (int) $dto['current_approver'] : null,
        ];
    }
}