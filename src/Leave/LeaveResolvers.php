<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Leave;

use ksfraser\FrontAccounting\Common\Workflow\CalcRegistry;
use ksfraser\FrontAccounting\HRM\Repository\LeaveBalanceRepository;
use ksfraser\FrontAccounting\HRM\Repository\LeaveRequestRepository;

/**
 * hrm.leave.* workflow resolvers (BR-HRM-01 FR-HRM-001-002/005/006/007).
 *
 * Registered into the BR-COM-02 Workflow CalcRegistry so ProcessDefinition
 * verb rows can reference them by key:
 *   hrm.leave.validate_submit — submit-entitlement guard data
 *   hrm.leave.check_balance   — approve-time balance re-check
 *   hrm.leave.consume_days    — writes the append-only day book + used_days
 *   hrm.approval_chain        — adapts ApproverChainService::nextStep for the
 *                               engine (always a resolver object).
 * Each resolver returns a `result` that transition guards read via
 * 'result.<path>' and pre `set` verbs copy into the DTO — so a later
 * resolver overwriting context['result'] never loses the earlier data.
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 */
class LeaveResolvers
{
    /** @var LeaveRequestRepository */
    private $requests;

    /** @var LeaveBalanceRepository */
    private $balances;

    /** @var ApproverChainService */
    private $chain;

    public function __construct(
        LeaveRequestRepository $requests,
        LeaveBalanceRepository $balances,
        ApproverChainService $chain
    ) {
        $this->requests = $requests;
        $this->balances = $balances;
        $this->chain    = $chain;
    }

    /**
     * Register every resolver into a CalcRegistry.
     *
     * @param CalcRegistry $calc
     */
    public function registerInto(CalcRegistry $calc): void
    {
        $calc->register('hrm.leave.validate_submit', function (array $dto, array $context = []) {
            return $this->validateSubmit($dto);
        });
        $calc->register('hrm.leave.check_balance', function (array $dto, array $context = []) {
            return $this->checkBalance($dto);
        });
        $calc->register('hrm.leave.consume_days', function (array $dto, array $context = []) {
            return $this->consumeDays($dto);
        });
        $calc->register('hrm.approval_chain', [$this->chain, 'nextStep']);
    }

    /**
     * Submit guard: structural validity + current entitlement
     * (BR-01 FR-HRM-001-002; entitlement re-checked at approve).
     *
     * @return array ['valid'=>bool, 'error'=>string|null,
     *                'balance_remaining'=>float, 'days_requested'=>float]
     */
    public function validateSubmit(array $dto): array
    {
        $personId = (int) ($dto['person_id'] ?? 0);
        $typeId   = (int) ($dto['leave_type_id'] ?? 0);
        $days     = (float) ($dto['days'] ?? 0);
        $from     = (string) ($dto['from_date'] ?? '');
        $to       = (string) ($dto['to_date'] ?? '');

        if ($personId <= 0) {
            return ['valid' => false, 'error' => 'person_id is required',
                    'balance_remaining' => 0.0, 'days_requested' => $days];
        }
        if ($typeId <= 0) {
            return ['valid' => false, 'error' => 'leave_type_id is required',
                    'balance_remaining' => 0.0, 'days_requested' => $days];
        }
        if ($from === '' || $to === '' || $from > $to) {
            return ['valid' => false, 'error' => 'invalid date range',
                    'balance_remaining' => 0.0, 'days_requested' => $days];
        }
        if ($days <= 0) {
            return ['valid' => false, 'error' => 'days must be positive',
                    'balance_remaining' => 0.0, 'days_requested' => $days];
        }

        $remaining = $this->balances->balanceRemaining(
            $personId, $typeId, (int) \substr($from, 0, 4)
        );

        if ($remaining < $days) {
            return ['valid' => false, 'error' => 'insufficient leave balance',
                    'balance_remaining' => (float) $remaining, 'days_requested' => $days];
        }

        return ['valid' => true, 'error' => null,
                'balance_remaining' => (float) $remaining, 'days_requested' => $days];
    }

    /**
     * Approve-time balance re-check + blocker detection (BR-01 req 3).
     *
     * @return array ['balance_remaining'=>float, 'days_requested'=>float,
     *                'blocker_request_id'=>int|null,
     *                'blocker_name'=>string|null]
     */
    public function checkBalance(array $dto): array
    {
        $personId = (int) ($dto['person_id'] ?? 0);
        $typeId   = (int) ($dto['leave_type_id'] ?? 0);
        $days     = (float) ($dto['days'] ?? 0);
        $from     = (string) ($dto['from_date'] ?? '');
        $year     = $from !== '' ? (int) \substr($from, 0, 4) : (int) \date('Y');

        $remaining = $this->balances->balanceRemaining($personId, $typeId, $year);

        $blocker = null;
        $blockerName = null;
        if ($remaining < $days) {
            $blk = $this->requests->findBlocker(
                $personId, $typeId, (string) $year, (int) ($dto['request_id'] ?? 0)
            );
            if ($blk) {
                $blocker     = (int) $blk['request_id'];
                $blockerName = (string) ($blk['requester_name'] ?? '');
            }
        }

        return [
            'balance_remaining'  => (float) $remaining,
            'days_requested'     => $days,
            'blocker_request_id' => $blocker,
            'blocker_name'       => $blockerName,
        ];
    }

    /**
     * consume_days: append one row per calendar date of the request and bump
     * used_days (BR-01 freq 9, FR-HRM-001-007). Runs on approved->scheduled.
     *
     * @return array ['days_booked'=>int]
     */
    public function consumeDays(array $dto): array
    {
        $requestId = (int) ($dto['request_id'] ?? 0);
        $from      = (string) ($dto['from_date'] ?? '');
        $to        = (string) ($dto['to_date'] ?? '');
        if ($requestId <= 0 || $from === '' || $to === '') {
            return ['days_booked' => 0];
        }

        $booked = 0;
        $step   = new \DateInterval('P1D');
        $start  = new \DateTime($from);
        $end    = new \DateTime($to);
        for ($d = clone $start; $d <= $end; $d->add($step)) {
            $this->requests->insertDay($requestId, $d->format('Y-m-d'), 1.0);
            $booked++;
        }

        $personId = (int) ($dto['person_id'] ?? 0);
        $typeId   = (int) ($dto['leave_type_id'] ?? 0);
        $year     = (int) \substr($from, 0, 4);
        if ($personId > 0 && $typeId > 0) {
            $this->balances->incrementUsed($personId, $typeId, $year, (float) ($dto['days'] ?? $booked));
        }

        return ['days_booked' => $booked];
    }
}