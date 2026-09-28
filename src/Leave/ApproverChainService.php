<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Leave;

use ksfraser\FrontAccounting\HRM\Repository\LeaveRequestRepository;

/**
 * Approver chain resolution (BR-HRM-01 FR-HRM-001-004).
 *
 * The chain is the employee's reports_to walk over
 * 0_hrm_contacts_employment (person_id -> reports_to_person_id), ordered
 * front-line manager first, up to the top. It is a BR-COM-02 transition
 * pre-action resolver (`hrm.approval_chain.nextStep`), NOT new process
 * logic: nextStep() decides whether the *currently acting* approver is the
 * last in the chain and returns who routes next.
 *
 * Engine constraint honoured: ProcessDefinition rejects self-loops, so a
 * multi-level chain advances by resolver + guard:
 *   nextStep() sets the dto's current_approver + is_final;
 *   the approve-edge guard requires is_final == 1.
 * A non-final approve therefore fails the guard with the dto already
 * advanced (current_approver = next level), leaving the workflow state
 * unchanged at `pending`; the caller persists the new approver + notifies.
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 */
class ApproverChainService
{
    public const MAX_DEPTH = 10;

    /** @var LeaveRequestRepository */
    private $requests;

    public function __construct(LeaveRequestRepository $requests)
    {
        $this->requests = $requests;
    }

    /**
     * Resolve the full reports_to chain for an employee (manager-first).
     *
     * @return int[] person ids, oldest-level last
     */
    public function chainFor(int $personId): array
    {
        $chain    = [];
        $current  = $personId;
        $seen     = [];
        for ($i = 0; $i < self::MAX_DEPTH; $i++) {
            if (isset($seen[$current])) {
                break; // cycle protection
            }
            $seen[$current] = true;
            $manager = $this->reportsTo($current);
            if ($manager === null || $manager <= 0) {
                break;
            }
            $chain[] = $manager;
            $current = $manager;
        }
        return $chain;
    }

    /**
     * BR-COM-02 resolver: advance the chain for the current approver.
     *
     * dto needs: person_id (requester), current_approver (acting level,
     * null on first routing).
     *
     * Returns: ['person_id' => acting level, 'next_approver' => next person
     *           or acting when final, 'is_final' => bool, 'chain' => array]
     */
    public function nextStep(array $dto, array $context = []): array
    {
        $requester = (int) ($dto['person_id'] ?? 0);
        $chain     = $this->chainFor($requester);
        if ($chain === []) {
            // No reports_to at all: requester self-approves.
            return [
                'person_id'     => $requester,
                'next_approver' => $requester,
                'is_final'      => true,
                'chain'         => [],
            ];
        }

        $current = isset($dto['current_approver']) ? (int) $dto['current_approver'] : 0;
        if ($current === 0) {
            // First routing: head of chain. Single-manager chain is final.
            $head      = $chain[0];
            $lastIndex = count($chain) - 1;
            return [
                'person_id'     => $head,
                'next_approver' => $head,
                'is_final'      => $lastIndex === 0,
                'chain'         => $chain,
            ];
        }

        $pos = \array_search($current, $chain, true);
        if ($pos === false) {
            // Actor not in chain: refuse (should not happen; access-gated).
            return [
                'person_id'     => $current,
                'next_approver' => $current,
                'is_final'      => true,
                'chain'         => $chain,
            ];
        }

        $lastIndex = count($chain) - 1;
        if ($pos >= $lastIndex) {
            return [
                'person_id'     => $current,
                'next_approver' => $current,
                'is_final'      => true,
                'chain'         => $chain,
            ];
        }

        $next = $chain[$pos + 1];
        return [
            'person_id'     => $next,
            'next_approver' => $next,
            'is_final'      => false,
            'chain'         => $chain,
        ];
    }

    private function reportsTo(int $personId): ?int
    {
        $sql = "SELECT reports_to_person_id FROM " . TB_PREF . "hrm_contacts_employment
            WHERE person_id = " . (int) $personId;
        $result = db_query($sql);
        $row = $result && db_num_rows($result) ? db_fetch_assoc($result) : null;
        return $row ? (int) $row['reports_to_person_id'] : null;
    }
}