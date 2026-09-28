<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Leave;

use ksfraser\FrontAccounting\Common\Workflow\StateMachine;
use ksfraser\FrontAccounting\HRM\Repository\LeaveRequestRepository;

/**
 * Leave workflow orchestration (BR-HRM-01 FR-HRM-001-002/003/004/007).
 *
 * Drives the BR-COM-02 StateMachine on behalf of pages/hooks:
 *   submit()   draft -> submitted (guard)  -> pending (route to chain head)
 *   approve()  pending ->                     multi-level chain advance
 *              (guard_failed, NOT final)     -> persist next + notify
 *              (final, balance ok)           -> approved -> scheduled
 *              (final, balance refused)      -> rejected_exhausted (auto)
 *   reject()   pending -> rejected (reason required)
 *   cancel()   draft/pending -> cancelled
 *
 * The StateMachine persists workflow state + history; THIS service keeps the
 * leave request row's `state` and `current_approver_id` mirrored. Chain
 * advance persists even when the engine guard refused (the engine leaves
 * state at `pending` but the resolver already advanced dto.current_approver).
 *
 * A dto carries: request_id, person_id, leave_type_id, from_date, to_date,
 * days, reason, current_approver, is_final, balance_remaining.
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 */
class LeaveService
{
    public const TYPE_PENDING  = 'leave_approval';
    public const TYPE_APPROVED = 'leave_approved';
    public const TYPE_REJECTED = 'leave_rejected';
    public const TYPE_EXHAUSTED = 'leave_rejected_exhausted';

    /** @var LeaveRequestRepository */
    private $requests;

    /** @var StateMachine */
    private $sm;

    /** @var LeaveNotifier */
    private $notifier;

    /** @var \ksfraser\FrontAccounting\Common\Notification\Inbox\Notifier|null */
    private $mail;

    public function __construct(
        LeaveRequestRepository $requests,
        StateMachine $sm,
        LeaveNotifier $notifier,
        $mail = null
    ) {
        $this->requests = $requests;
        $this->sm       = $sm;
        $this->notifier = $notifier;
        $this->mail     = $mail;
    }

    /**
     * Submit a new leave request: insert draft, run submit guard, promote to
     * pending (route to first approver). Notifies the approver on success.
     *
     * @return array ['ok'=>bool, 'request_id'=>int, 'state'=>string,
     *                'error'=>?string, 'transition'=>array]
     */
    public function submit(array $data): array
    {
        $requestId = $this->requests->insertRequest($data);
        $dto       = $this->toDto($data, $requestId);

        $submitted = $this->sm->transition(
            LeaveProcessDefinition::RECORD,
            (string) $requestId,
            'submitted',
            $dto,
            (string) ($data['actor'] ?? ''),
            'submit'
        );

        if (!$submitted['ok']) {
            $error = $submitted['error'];
            if ($error === 'guard_failed' && !empty($submitted['dto']['error'])) {
                $error = (string) $submitted['dto']['error'];
            }
            return ['ok' => false, 'request_id' => $requestId, 'state' => 'draft',
                    'error' => $error, 'transition' => $submitted];
        }

        $pending = $this->sm->transition(
            LeaveProcessDefinition::RECORD,
            (string) $requestId,
            'pending',
            $submitted['dto'],
            (string) ($data['actor'] ?? ''),
            'route to approval chain'
        );

        $dtoAfter = $pending['ok'] ? $pending['dto'] : $submitted['dto'];
        $this->persist($requestId, $dtoAfter);

        if ($pending['ok']) {
            $this->notifyApprover($requestId, $dtoAfter);
        }

        return ['ok' => $pending['ok'], 'request_id' => $requestId,
                'state' => $pending['ok'] ? 'pending' : 'submitted',
                'error' => $pending['ok'] ? null : $pending['error'],
                'transition' => $pending];
    }

    /**
     * Approve at the current level. Multi-level chains advance one level per
     * call; only the final level flips to approved -> scheduled.
     *
     * @return array ['ok'=>bool, 'advanced'=>bool, 'state'=>string,
     *                'error'=>?string, 'transition'=>array]
     */
    public function approve(int $requestId, string $actor = ''): array
    {
        $row = $this->requests->findByRequestId($requestId);
        if (!$row) {
            return ['ok' => false, 'advanced' => false, 'state' => '',
                    'error' => 'request not found', 'transition' => []];
        }
        $dto = $this->fromRow($row);

        $result = $this->sm->transition(
            LeaveProcessDefinition::RECORD,
            (string) $requestId,
            'approved',
            $dto,
            $actor,
            'approve'
        );

        if (!$result['ok']) {
            $dtoAfter = $result['dto'];

            if ($result['error'] === 'guard_failed' && !($dtoAfter['is_final'] ?? false)) {
                // Mid-chain: guard refused because not final. Persist the
                // advanced current_approver + notify next level; stay pending.
                $this->persist($requestId, $dtoAfter);
                $this->notifyApprover($requestId, $dtoAfter);
                return ['ok' => false, 'advanced' => true, 'state' => 'pending',
                        'error' => null, 'transition' => $result];
            }

            if ($result['error'] === 'guard_failed' && ($dtoAfter['is_final'] ?? false)) {
                // Final level but balance refused: auto-reject exhausted.
                $auto = $this->sm->transition(
                    LeaveProcessDefinition::RECORD,
                    (string) $requestId,
                    'rejected_exhausted',
                    $dtoAfter,
                    $actor,
                    'insufficient balance'
                );
                $out = $auto['ok'] ? $auto['dto'] : $dtoAfter;
                $this->persist($requestId, $out);
                $this->notifyOutcome($requestId, $out, self::TYPE_EXHAUSTED);
                return ['ok' => false, 'advanced' => false,
                        'state' => 'rejected_exhausted',
                        'error' => 'insufficient leave balance',
                        'transition' => $auto];
            }

            // genuine failure (access_denied, illegal_transition, ...)
            return ['ok' => false, 'advanced' => false,
                    'state' => $row['state'] ?? '',
                    'error' => $result['error'], 'transition' => $result];
        }

        // Approved: mirror to scheduled (consume_days fires in the auto edge).
        $scheduled = $this->sm->transition(
            LeaveProcessDefinition::RECORD,
            (string) $requestId,
            'scheduled',
            $result['dto'],
            $actor,
            'consume days'
        );
        $final = $scheduled['ok'] ? $scheduled['dto'] : $result['dto'];
        $this->persist($requestId, $final);
        $this->notifyOutcome($requestId, $final, self::TYPE_APPROVED);

        return ['ok' => true, 'advanced' => false, 'state' => 'scheduled',
                'error' => null, 'transition' => $scheduled];
    }

    /**
     * Reject pending request. Reason is mandatory (BR-01 req 4).
     *
     * @return array ['ok'=>bool, 'state'=>string, 'error'=>?string]
     */
    public function reject(int $requestId, string $reason, string $actor = ''): array
    {
        if (trim($reason) === '') {
            return ['ok' => false, 'state' => 'pending', 'error' => 'reason required',
                    'transition' => []];
        }

        $row = $this->requests->findByRequestId($requestId);
        if (!$row) {
            return ['ok' => false, 'state' => '', 'error' => 'request not found',
                    'transition' => []];
        }
        $dto = $this->fromRow($row);

        $result = $this->sm->transition(
            LeaveProcessDefinition::RECORD,
            (string) $requestId,
            'rejected',
            $dto,
            $actor,
            $reason
        );
        if (!$result['ok']) {
            return ['ok' => false, 'state' => $row['state'] ?? '',
                    'error' => $result['error'], 'transition' => $result];
        }

        $this->persist($requestId, $result['dto']);
        $this->notifyOutcome($requestId, $result['dto'], self::TYPE_REJECTED);
        return ['ok' => true, 'state' => 'rejected', 'error' => null,
                'transition' => $result];
    }

    /**
     * Cancel a draft or pending request (owner/approver).
     *
     * @return array
     */
    public function cancel(int $requestId, string $actor = ''): array
    {
        $row = $this->requests->findByRequestId($requestId);
        if (!$row) {
            return ['ok' => false, 'state' => '', 'error' => 'request not found',
                    'transition' => []];
        }
        $dto = $this->fromRow($row);
        $result = $this->sm->transition(
            LeaveProcessDefinition::RECORD,
            (string) $requestId,
            'cancelled',
            $dto,
            $actor,
            'cancelled'
        );
        if (!$result['ok']) {
            return ['ok' => false, 'state' => $row['state'] ?? '',
                    'error' => $result['error'], 'transition' => $result];
        }
        $this->persist($requestId, $result['dto']);
        return ['ok' => true, 'state' => 'cancelled', 'error' => null,
                'transition' => $result];
    }

    /** allowedTransitions exposes user-triggered, access-gated actions to UI. */
    public function allowedActions(int $requestId): array
    {
        return $this->sm->allowedTransitions(
            LeaveProcessDefinition::RECORD,
            (string) $requestId,
            $this->requests->findByRequestId($requestId) ?: []
        );
    }

    private function persist(int $requestId, array $dto): void
    {
        $state = (string) ($dto['state'] ?? '');
        $approver = isset($dto['current_approver']) && (int) $dto['current_approver'] > 0
            ? (int) $dto['current_approver'] : null;
        $this->requests->updateState($requestId, $state, $approver);
    }

    private function notifyApprover(int $requestId, array $dto): void
    {
        if ($this->mail === null) {
            return;
        }
        $approver = $dto['current_approver'] ?? null;
        if ($approver && (int) $approver > 0) {
            $this->notifier->notifyPerson($this->mail, (int) $approver,
                self::TYPE_PENDING, $dto + ['request_id' => $requestId]);
        }
    }

    private function notifyOutcome(int $requestId, array $dto, string $type): void
    {
        if ($this->mail === null) {
            return;
        }
        $dto = $dto + ['request_id' => $requestId];
        $this->notifier->notifyPerson($this->mail, (int) ($dto['person_id'] ?? 0),
            $type, $dto);
        $this->notifier->notifyApproveRole($this->mail, $type, $dto);
    }

    private function toDto(array $data, int $requestId): array
    {
        return [
            'request_id'       => $requestId,
            'person_id'        => (int) ($data['person_id'] ?? 0),
            'leave_type_id'    => (int) ($data['leave_type_id'] ?? 0),
            'from_date'        => (string) ($data['from_date'] ?? ''),
            'to_date'          => (string) ($data['to_date'] ?? ''),
            'days'             => (float) ($data['days'] ?? 0),
            'reason'           => (string) ($data['reason'] ?? ''),
            'state'            => 'draft',
            'current_approver' => null,
        ];
    }

    private function fromRow(array $row): array
    {
        return [
            'request_id'       => (int) $row['request_id'],
            'person_id'        => (int) $row['person_id'],
            'leave_type_id'    => (int) $row['leave_type_id'],
            'from_date'        => (string) $row['from_date'],
            'to_date'          => (string) $row['to_date'],
            'days'             => (float) $row['days'],
            'reason'           => (string) ($row['reason'] ?? ''),
            'state'            => (string) ($row['state'] ?? 'draft'),
            'current_approver' => isset($row['current_approver_id'])
                && (int) $row['current_approver_id'] > 0
                ? (int) $row['current_approver_id'] : null,
        ];
    }
}