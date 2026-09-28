<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ksfraser\FrontAccounting\Common\Notification\Inbox\InMemoryInboxStore;
use ksfraser\FrontAccounting\Common\Workflow\InMemoryStateStore;
use ksfraser\FrontAccounting\HRM\Leave\ApproverChainService;
use ksfraser\FrontAccounting\HRM\Leave\LeaveNotifier;
use ksfraser\FrontAccounting\HRM\Leave\LeaveProcessDefinition;
use ksfraser\FrontAccounting\HRM\Leave\LeaveService;
use ksfraser\FrontAccounting\HRM\Leave\LeaveWorkflowFactory;
use ksfraser\FrontAccounting\HRM\Repository\LeaveBalanceRepository;
use ksfraser\FrontAccounting\HRM\Repository\LeaveRequestRepository;

/**
 * Leave approval workflow — BR-COM-02 engine slice on leave_request
 * (BR-HRM-01 FR-HRM-001-002/003/004/007, FR-COM-02-003).
 *
 * Exercise the full chain through the real StateMachine + resolvers with an
 * in-memory store and the stubbed FA db_* (this module's tests bootstrap).
 *
 * @BABOK Related: BR-HRM-01
 * @BABOK Related: BR-COM-02
 * @BABOK Related: FR-HRM-001-002
 * @BABOK Related: FR-HRM-001-003
 * @BABOK Related: FR-HRM-001-004
 * @BABOK Related: FR-HRM-001-007
 */
class LeaveWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__fa_select_queue'] = [];
        $GLOBALS['__fa_next_id'] = 100;

        $this->requests = new LeaveRequestRepository();
        $this->balances = new LeaveBalanceRepository();
        $this->chain    = new ApproverChainService($this->requests);
        $this->factory  = new LeaveWorkflowFactory($this->requests, $this->balances, $this->chain);

        // person -> user map: 2 (requester) -> 2, 7 (mgr1) -> 7, 8 (mgr2) -> 8
        $this->personToUser = static function (int $p): ?int {
            return in_array($p, [2, 7, 8], true) ? $p : null;
        };
        $this->inbox = new InMemoryInboxStore();

        $this->sm = $this->factory->assemble(
            new InMemoryStateStore(),
            /** allowed: everyone may view; approve area granted by default */
            static function (string $area): bool { return true; }
        );
        $this->notifier = new LeaveNotifier($this->personToUser);
        $this->mail = new \ksfraser\FrontAccounting\Common\Notification\Inbox\Notifier(
            $this->inbox,
            new \ksfraser\FrontAccounting\Common\Notification\Inbox\RecipientResolver()
        );
        $this->svc = new LeaveService($this->requests, $this->sm, $this->notifier, $this->mail);

        // chain: 2 reports to 7 reports to 8 (top). One result set per query.
        $this->chainRowset = function (): array {
            return [
                [['reports_to_person_id' => 7]],
                [['reports_to_person_id' => 8]],
                [],
            ];
        };
        $this->balanceRow = ['balance_id' => 1, 'person_id' => 2, 'leave_type_id' => 1,
                             'year' => 2026, 'allocated_days' => 10,
                             'carried_over' => 0, 'used_days' => 0];
        $this->requestRow = static function (string $state, int $approver): array {
            return [['request_id' => 100, 'person_id' => 2, 'leave_type_id' => 1,
                     'from_date' => '2026-01-05', 'to_date' => '2026-01-07',
                     'days' => 3.0, 'reason' => 'holiday', 'state' => $state,
                     'current_approver_id' => $approver]];
        };
        $this->sub = [
            'person_id' => 2, 'leave_type_id' => 1,
            'from_date' => '2026-01-05', 'to_date' => '2026-01-07',
            'days' => 3.0, 'reason' => 'holiday', 'actor' => 'u2',
        ];
    }

    private function seedSubmitQueue(): void
    {
        $q = [];
        $q[] = [$this->balanceRow];          // validate_submit -> balanceRemaining(2,1,2026)
        foreach (($this->chainRowset)() as $set) {
            $q[] = $set;                     // chainFor(2)
        }
        $GLOBALS['__fa_select_queue'] = $q;
    }

    private function seedApproveQueue(string $state = 'pending', int $approver = 7, ?array $balance = null): void
    {
        $q = [];
        $q[] = ($this->requestRow)($state, $approver);   // findByRequestId
        foreach (($this->chainRowset)() as $set) {
            $q[] = $set;                               // chainFor(2) in pre
        }
        $q[] = [$balance ?: $this->balanceRow];        // check_balance -> balanceRemaining
        $GLOBALS['__fa_select_queue'] = $q;
    }

    public function testSubmitRoutesToChainHeadAndPending(): void
    {
        $this->seedSubmitQueue();

        $res = $this->svc->submit($this->sub);

        $this->assertTrue($res['ok']);
        $this->assertSame('pending', $res['state']);
        $this->assertSame(7, $res['transition']['dto']['current_approver'] ?? null);
    }

    public function testInsufficientBalanceStaysDraft(): void
    {
        $low = $this->balanceRow;
        $low['allocated_days'] = 1; // 1 < 3 requested
        $q = [[$low]];             // validate_submit -> balanceRemaining
        $GLOBALS['__fa_select_queue'] = $q;

        $res = $this->svc->submit($this->sub);

        $this->assertFalse($res['ok']);
        $this->assertSame('draft', $res['state']);
        $this->assertSame('insufficient leave balance', $res['error']);
    }

    public function testMultiLevelChainAdvancesUntilFinalThenSchedules(): void
    {
        $this->seedSubmitQueue();
        $submit = $this->svc->submit($this->sub);
        $this->assertTrue($submit['ok']);

        // Level 1 approve (mgr 7): -> chain next (8), NOT final -> guard refused
        $this->seedApproveQueue('pending', 7);
        $r1 = $this->svc->approve(100);
        $this->assertFalse($r1['ok']);
        $this->assertTrue($r1['advanced']);
        $this->assertSame('pending', $r1['state']);
        $this->assertSame(8, $r1['transition']['dto']['current_approver'] ?? null);

        // Level 2 approve (mgr 8): final + balance => approved -> scheduled
        $this->seedApproveQueue('pending', 8);
        // consume_days (approved->scheduled auto) does: findBalance read (for incrementUsed)
        $GLOBALS['__fa_select_queue'][] = [$this->balanceRow];
        $r2 = $this->svc->approve(100);
        $this->assertTrue($r2['ok']);
        $this->assertSame('scheduled', $r2['state']);

        $this->assertSame([], $this->svc->allowedActions(100));
    }

    public function testFinalLevelInsufficientBalanceAutoRejectsExhausted(): void
    {
        $this->seedSubmitQueue();
        $submit = $this->svc->submit($this->sub);
        $this->assertTrue($submit['ok']);

        // advance to level 2 (mgr 8) first
        $this->seedApproveQueue('pending', 7);
        $r1 = $this->svc->approve(100);
        $this->assertTrue($r1['advanced']);

        // final approve with balance 1 < 3 -> guard refused at the final level
        $low = $this->balanceRow;
        $low['allocated_days'] = 1;
        $this->seedApproveQueue('pending', 8, $low);

        $r2 = $this->svc->approve(100);
        $this->assertFalse($r2['ok']);
        $this->assertSame('rejected_exhausted', $r2['state']);
        $this->assertSame('insufficient leave balance', $r2['error']);
    }

    public function testRejectRequiresReason(): void
    {
        $this->seedSubmitQueue();
        $this->svc->submit($this->sub);

        $res = $this->svc->reject(100, '');
        $this->assertFalse($res['ok']);
        $this->assertSame('reason required', $res['error']);
    }

    public function testRejectPendingWithReasonEndsRejected(): void
    {
        $this->seedSubmitQueue();
        $this->svc->submit($this->sub);

        $q = [];
        $q[] = ($this->requestRow)('pending', 7);
        $GLOBALS['__fa_select_queue'] = $q;

        $res = $this->svc->reject(100, 'manager not available');
        $this->assertTrue($res['ok']);
        $this->assertSame('rejected', $res['state']);
    }

    public function testAccessDeniedWithoutApproveArea(): void
    {
        $sm = $this->factory->assemble(
            new InMemoryStateStore(),
            static function (string $area): bool { return $area !== 'SA_LEAVE_APPROVE'; }
        );
        $svc = new LeaveService($this->requests, $sm, $this->notifier, $this->mail);

        $this->seedSubmitQueue();
        $submit = $svc->submit($this->sub);
        $this->assertTrue($submit['ok']);

        $this->seedApproveQueue('pending', 7);
        $res = $svc->approve(100);
        $this->assertFalse($res['ok']);
        $this->assertSame('access_denied', $res['error']);
    }

    public function testDefinitionIsValidLeavesOwnedByModule(): void
    {
        $def = LeaveProcessDefinition::build();
        $this->assertSame('leave_request.approval', $def->getName());
        $this->assertSame('leave_request', $def->getRecord());
        $this->assertSame('ksf_FA_HRM', $def->getRecordPrefix());
        $this->assertSame('draft', $def->getInitial());
        foreach ($def->getTransitions() as $t) {
            $this->assertNotSame($t['from'], $t['to'], 'no self-loop');
        }
    }
}