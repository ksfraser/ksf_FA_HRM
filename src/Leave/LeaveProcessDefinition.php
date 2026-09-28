<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Leave;

use ksfraser\FrontAccounting\Common\Workflow\ProcessDefinition;

/**
 * Leave approval process definition — BR-COM-02 worked example for the
 * leave_request record (BR-HRM-01 FR-HRM-001-002, FR-COM-02-002).
 *
 * States: draft, submitted, pending, scheduled, approved, rejected,
 * rejected_exhausted, cancelled.
 *
 * Edge map (guard + resolver semantics match BR-COM-02 §leave):
 *   draft            -> submitted          pre: validate_fields (submit guard)
 *   submitted        -> pending            auto: route to first approver
 *   pending          -> approved           pre: check_balance + approve guard
 *   pending          -> rejected           user (prompt reason required)
 *   pending          -> rejected_exhausted auto: balance guard refused
 *   approved         -> scheduled          auto: consume_days (balance book)
 *
 * Multi-level chains live in a single `pending` state (the engine rejects
 * self-loops); advance is handled by the hrm.approval_chain.nextStep
 * resolver, which mutates dto.current_approver + is_final. When the acting
 * approver is NOT final, the approve guard fails (guard_failed) with the dto
 * already advanced, the service persists the new current_approver and
 * notifies the next approver, and the workflow stays `pending`.
 *
 * @package ksf_FA_HRM
 * @since   1.0.0
 */
final class LeaveProcessDefinition
{
    public const RECORD        = 'leave_request';
    public const RECORD_PREFIX = 'ksf_FA_HRM';
    public const PROCESS_NAME  = 'leave_request.approval';

    /** @return string[] every state this process owns */
    public static function states(): array
    {
        return ['draft', 'submitted', 'pending', 'scheduled',
                'approved', 'rejected', 'rejected_exhausted', 'cancelled'];
    }

    public static function initial(): string
    {
        return 'draft';
    }

    /**
     * Build the definition (validates acyclicity + shape at construction).
     */
    public static function build(): ProcessDefinition
    {
        return new ProcessDefinition(
            self::PROCESS_NAME,
            self::RECORD,
            self::initial(),
            self::states(),
            [
                // Submit: structural + entitlement guard (BR-01 FR-HRM-001-002).
                [
                    'from'    => 'draft',
                    'to'      => 'submitted',
                    'trigger' => 'user',
                    'label'   => 'Submit',
                    'pre'     => [
                        ['call', 'hrm.leave.validate_submit'],
                        ['set', 'valid', '=', 'result.valid'],
                        ['set', 'error', '=', 'result.error'],
                    ],
                    'guard' => [
                        ['result.valid', 'eq', 1],
                        ['result.balance_remaining', 'gte', 'result.days_requested'],
                    ],
                ],

                // Admin/owner cancel before routing.
                [
                    'from'    => 'draft',
                    'to'      => 'cancelled',
                    'trigger' => 'user',
                    'label'   => 'Cancel',
                ],

                // Auto-promote to pending: first approver is chain head.
                [
                    'from'    => 'submitted',
                    'to'      => 'pending',
                    'trigger' => 'auto',
                    'pre'     => [
                        ['resolver', 'hrm.approval_chain', 'nextStep'],
                        ['set', 'current_approver', '=', 'result.person_id'],
                    ],
                ],

                // Approve (per-level). Multi-level: guard_failed when the
                // acting approver isn't final OR the balance refused. Guard
                // reads DTO fields (set verb copies resolver output into the
                // dto BEFORE the balance call overwrites context['result']).
                [
                    'from'    => 'pending',
                    'to'      => 'approved',
                    'trigger' => 'user',
                    'label'   => 'Approve',
                    'access'  => 'SA_LEAVE_APPROVE',
                    'pre'     => [
                        ['resolver', 'hrm.approval_chain', 'nextStep'],
                        ['set', 'current_approver', '=', 'result.person_id'],
                        ['set', 'is_final', '=', 'result.is_final'],
                        ['call', 'hrm.leave.check_balance'],
                        ['set', 'balance_remaining', '=', 'result.balance_remaining'],
                        ['set', 'days', '=', 'result.days_requested'],
                    ],
                    'guard' => [
                        ['is_final', 'eq', 1],
                        ['balance_remaining', 'gte', 'dto.days'],
                    ],
                    'post' => [
                        ['broadcast', 'leave_approved', ['dto']],
                    ],
                ],

                // Reject: explicit reason mandated by BR-01.
                [
                    'from'    => 'pending',
                    'to'      => 'rejected',
                    'trigger' => 'user',
                    'label'   => 'Reject',
                    'access'  => 'SA_LEAVE_APPROVE',
                    'prompt'  => [['reason', '=', 'required']],
                    'post'    => [
                        ['broadcast', 'leave_rejected', ['dto']],
                    ],
                ],

                // Auto rejection when the last-level approval hits a block
                // (balance used up by a concurrent request, BR-01 req 3).
                // Guard reads DTO fields populated by the pre verbs.
                [
                    'from'    => 'pending',
                    'to'      => 'rejected_exhausted',
                    'trigger' => 'auto',
                    'pre'     => [
                        ['resolver', 'hrm.approval_chain', 'nextStep'],
                        ['set', 'current_approver', '=', 'result.person_id'],
                        ['set', 'is_final', '=', 'result.is_final'],
                        ['call', 'hrm.leave.check_balance'],
                        ['set', 'balance_remaining', '=', 'result.balance_remaining'],
                        ['set', 'days', '=', 'result.days_requested'],
                        ['set', 'reason', '=', 'insufficient leave balance'],
                    ],
                    'guard' => [
                        ['is_final', 'eq', 1],
                        ['balance_remaining', 'lt', 'dto.days'],
                    ],
                ],

                // Approved -> scheduled: consume_days writes per-date rows
                // and bumps balance.used_days (BR-01 req 9, FR-HRM-001-007).
                [
                    'from'    => 'approved',
                    'to'      => 'scheduled',
                    'trigger' => 'auto',
                    'pre'     => [
                        ['call', 'hrm.leave.consume_days'],
                    ],
                ],
            ],
            ['on' => 'after_save', 'criteria' => [['status', 'eq', 'submitted']]],
            self::RECORD_PREFIX
        );
    }
}