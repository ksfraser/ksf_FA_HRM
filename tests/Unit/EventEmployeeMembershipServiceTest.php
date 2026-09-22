<?php

declare(strict_types=1);

namespace Ksfraser\Tests\Unit\FAHRM;

use ksfraser\FrontAccounting\HRM\Repository\EventEmployeeMembershipRepository;
use ksfraser\FrontAccounting\HRM\Service\EventEmployeeMembershipService;
use PHPUnit\Framework\TestCase;

/**
 * EventEmployeeMembershipService coverage — FR-HRM-007-001 / UC-HRM-007-001.
 *
 * Uses the shared FA-db stubs from tests/stubs.php:
 *   - $GLOBALS['__fa_select_queue'] one seeded result-set per SELECT
 *     (each result-set is an array of assoc rows)
 *   - $GLOBALS['__fa_last_sql'] captured by db_query()
 *   - $GLOBALS['__fa_current_result'] consumed by db_fetch_assoc()
 *
 * @BABOK Related: FR-HRM-007-001 (REQ-1..REQ-5), UC-HRM-007-001, BR-007
 * @since 1.0.0
 */
class EventEmployeeMembershipServiceTest extends TestCase
{
    /** @var EventEmployeeMembershipService */
    private $service;

    protected function setUp(): void
    {
        $GLOBALS['__fa_select_queue']    = array();
        $GLOBALS['__fa_select_result']   = array();
        $GLOBALS['__fa_current_result']  = array();
        $GLOBALS['__fa_last_sql']        = '';
        $GLOBALS['__fa_next_id']         = 1;
        $GLOBALS['__fa_last_insert_id']  = 1;

        $this->service = new EventEmployeeMembershipService();
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function seedPersons(array $rows): void
    {
        // findPersonsByEmails issues exactly ONE SELECT → one result-set.
        $GLOBALS['__fa_select_queue'][] = $rows;
    }

    private function dto(array $extra = array()): array
    {
        return array_merge(array(
            'event_id'        => 83,
            'event_type'      => 'training',
            'linked_entities' => array(array('entity_type' => 'training')),
            'attendee_emails' => array('alice@example.com'),
            'started_at'      => '2026-09-14 09:00:00',
            'closed_at'       => '2026-09-14 17:00:00',
        ), $extra);
    }

    public function testClassifyAttendeesAppendsActiveMemberByRef(): void
    {
        $this->seedPersons(array(
            array('person_id' => 5, 'email' => 'alice@example.com', 'is_active' => 1),
        ));

        $data = array('dto' => $this->dto(), 'classification' => array('member' => array(), 'external' => array()));

        $this->service->classifyAttendees($data);

        $this->assertSame(array('alice@example.com'), $data['classification']['member']);
        $this->assertSame(array(), $data['classification']['external']);
    }

    public function testClassifyAttendeesEmailsAreLowerCasedAndDeduplicated(): void
    {
        $this->seedPersons(array(
            array('person_id' => 5, 'email' => 'alice@example.com', 'is_active' => 1),
        ));

        $data = array(
            'dto'            => $this->dto(array('attendee_emails' => array('ALICE@example.com', 'alice@example.com'))),
            'classification' => array('member' => array(), 'external' => array()),
        );

        $this->service->classifyAttendees($data);

        $this->assertCount(1, $data['classification']['member']);
        $this->assertSame(array('alice@example.com'), $data['classification']['member']);
    }

    public function testClassifyAttendeesInactiveEmploymentIsExternal(): void
    {
        $this->seedPersons(array(
            array('person_id' => 6, 'email' => 'bob@consultco.com', 'is_active' => 0),
        ));

        $data = array(
            'dto'            => $this->dto(array('attendee_emails' => array('bob@consultco.com'))),
            'classification' => array('member' => array(), 'external' => array()),
        );

        $this->service->classifyAttendees($data);

        $this->assertSame(array('bob@consultco.com'), $data['classification']['external']);
        $this->assertSame(array(), $data['classification']['member']);
    }

    public function testClassifyAttendeesNoEmploymentRowIsExternal(): void
    {
        $this->seedPersons(array(
            array('person_id' => 7, 'email' => 'consult@agency.io', 'is_active' => null),
        ));

        $data = array(
            'dto'            => $this->dto(array('attendee_emails' => array('consult@agency.io'))),
            'classification' => array('member' => array(), 'external' => array()),
        );

        $this->service->classifyAttendees($data);

        $this->assertSame(array('consult@agency.io'), $data['classification']['external']);
    }

    public function testClassifyAttendeesUnknownEmailIsUnclassified(): void
    {
        $this->seedPersons(array());

        $data = array(
            'dto'            => $this->dto(array('attendee_emails' => array('ghost@example.com'))),
            'classification' => array('member' => array(), 'external' => array()),
        );

        $this->service->classifyAttendees($data);

        $this->assertSame(array(), $data['classification']['member']);
        $this->assertSame(array(), $data['classification']['external']);
    }

    public function testClassifyAttendeesIsNoopOnNonHrTrack(): void
    {
        $data = array(
            'dto'            => $this->dto(array('event_type' => 'board_meeting',
                'linked_entities' => array(array('entity_type' => 'board')))),
            'classification' => array('member' => array(), 'external' => array()),
        );

        $this->service->classifyAttendees($data);

        $this->assertSame(array(), $data['classification']['member']);
        $this->assertSame(array(), $data['classification']['external']);
        $this->assertSame('', $GLOBALS['__fa_last_sql']); // no SELECT issued
    }

    public function testClassifyAttendeesHrTrackViaLinkedEntityToken(): void
    {
        $this->seedPersons(array(
            array('person_id' => 8, 'email' => 'carla@example.com', 'is_active' => 1),
        ));

        $data = array(
            'dto'            => $this->dto(array('event_type' => 'project',
                'linked_entities' => array(array('entity_type' => 'category')),
                'attendee_emails' => array('carla@example.com'))),
            'classification' => array('member' => array(), 'external' => array()),
        );

        $this->service->classifyAttendees($data);

        $this->assertSame(array('carla@example.com'), $data['classification']['member']);
    }

    public function testClassifyAttendeesRepoFailureIsSwallowed(): void
    {
        $this->seedPersons(array());

        $boom = new class extends EventEmployeeMembershipRepository {
            public function findPersonsByEmails(array $emails): array
            {
                throw new \RuntimeException('db down');
            }
        };

        $service = new EventEmployeeMembershipService($boom);
        $data = array('dto' => $this->dto(), 'classification' => array('member' => array(), 'external' => array()));

        $service->classifyAttendees($data); // must not throw

        $this->assertSame(array(), $data['classification']['member']);
        $this->assertSame(array(), $data['classification']['external']);
    }

    public function testRecordWorkedWindowsInsertsOneRowPerActiveMember(): void
    {
        $this->seedPersons(array(
            array('person_id' => 5, 'email' => 'alice@example.com', 'is_active' => 1),
            array('person_id' => 6, 'email' => 'bob@consultco.com', 'is_active' => 0),
        ));

        $count = $this->service->recordWorkedWindows($this->dto(array(
            'attendee_emails' => array('alice@example.com', 'bob@consultco.com'),
        )));

        $this->assertSame(1, $count);
        $this->assertStringContainsString('INSERT IGNORE', $GLOBALS['__fa_last_sql']);
        $this->assertStringContainsString('hrm_event_windows', $GLOBALS['__fa_last_sql']);
        $this->assertStringContainsString('83', $GLOBALS['__fa_last_sql']);      // event_id
        $this->assertStringContainsString('5', $GLOBALS['__fa_last_sql']);       // alice person_id
    }

    public function testRecordWorkedWindowsIsIdempotentByUniqueKey(): void
    {
        // INSERT IGNORE + UNIQUE(event_id, person_id): re-close cannot duplicate.
        $this->seedPersons(array(
            array('person_id' => 5, 'email' => 'alice@example.com', 'is_active' => 1),
        ));

        $this->service->recordWorkedWindows($this->dto());

        $this->assertStringContainsString('INSERT IGNORE', $GLOBALS['__fa_last_sql']);
    }

    public function testRecordWorkedWindowsSkipsNonHrTrack(): void
    {
        $this->seedPersons(array(
            array('person_id' => 5, 'email' => 'alice@example.com', 'is_active' => 1),
        ));

        $count = $this->service->recordWorkedWindows($this->dto(array(
            'event_type' => 'board_meeting',
            'linked_entities' => array(array('entity_type' => 'board')),
        )));

        $this->assertSame(0, $count);
        $this->assertSame('', $GLOBALS['__fa_last_sql']); // no SELECT, no INSERT
    }

    public function testRecordWorkedWindowsSkipsOpenEvent(): void
    {
        $this->seedPersons(array(
            array('person_id' => 5, 'email' => 'alice@example.com', 'is_active' => 1),
        ));

        $count = $this->service->recordWorkedWindows($this->dto(array(
            'closed_at' => '',
        )));

        $this->assertSame(0, $count);
        $this->assertSame('', $GLOBALS['__fa_last_sql']);
    }

    public function testRecordWorkedWindowsRepoFailureIsSwallowed(): void
    {
        $boom = new class extends EventEmployeeMembershipRepository {
            public function findPersonsByEmails(array $emails): array
            {
                throw new \RuntimeException('db down');
            }
        };

        $service = new EventEmployeeMembershipService($boom);

        $this->assertSame(0, $service->recordWorkedWindows($this->dto()));
    }

    public function testRepositoryCountWindows(): void
    {
        $GLOBALS['__fa_select_queue'][] = array(
            array('window_id' => 1),
            array('window_id' => 2),
            array('window_id' => 3),
        );

        $this->assertSame(3, (new EventEmployeeMembershipRepository())->countWindows());
        $this->assertStringContainsString('SELECT window_id FROM', $GLOBALS['__fa_last_sql']);
    }
}