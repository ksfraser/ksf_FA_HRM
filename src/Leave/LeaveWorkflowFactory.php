<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Leave;

use ksfraser\FrontAccounting\Common\Workflow\CalcRegistry;
use ksfraser\FrontAccounting\Common\Workflow\Contract\StateStoreInterface;
use ksfraser\FrontAccounting\Common\Workflow\ProcessDefinition;
use ksfraser\FrontAccounting\Common\Workflow\StateMachine;
use ksfraser\FrontAccounting\Common\Workflow\StepEngine;
use ksfraser\FrontAccounting\Common\Workflow\WorkflowRegistry;
use ksfraser\FrontAccounting\HRM\Repository\LeaveBalanceRepository;
use ksfraser\FrontAccounting\HRM\Repository\LeaveRequestRepository;

/**
 * Leave workflow factory — DI-assembles the full BR-COM-02 stack for the
 * leave_request record (BR-HRM-01 FR-HRM-001-002, FR-COM-02-003).
 *
 * One assemble() yields a ready StateMachine: CalcRegistry with the
 * hrm.leave.* resolvers + the definition registered on it. FA runtime passes
 * the FaStateStore + a can_access_page() check; tests pass InMemoryStateStore
 * + a stubbed check. Facts the engine needs are injected, nothing is global.
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 */
final class LeaveWorkflowFactory
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
     * Build a new engine + state machine.
     *
     * @param StateStoreInterface $store         FaStateStore (FA) / InMemoryStateStore (tests)
     * @param callable|null       $accessCheck   fn(string $area): bool
     *
     * @return StateMachine
     */
    public function assemble(StateStoreInterface $store, ?callable $accessCheck = null): StateMachine
    {
        $calc = new CalcRegistry();
        $resolvers = new LeaveResolvers($this->requests, $this->balances, $this->chain);
        $resolvers->registerInto($calc);

        $engine = new StepEngine(WorkflowRegistry::getInstance(), $calc);

        $sm = new StateMachine($store, $engine);
        $sm->register(LeaveProcessDefinition::build());
        $sm->setAccessCheck($accessCheck);

        return $sm;
    }
}