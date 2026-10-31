<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

use SupportManager\Sdk\Enums\Leave\LeaveDurationType;
use SupportManager\Sdk\Enums\Leave\LeaveStatus;

/**
 * A single leave (holiday) request — date range, duration type, status, and
 * the hours/sleeping-nights it consumes.
 *
 * Mirrors the platform's `LeaveRequestResource` attributes, plus the viewer's
 * permissions from the resource object's `meta.can` block. Those are null when
 * the response carries no `meta` (e.g. the admin pending queue). For `Whole
 * Days`, {@see $endDate} is the last day off. {@see $leaveType} is an open list
 * (`annual_leave`, `sick_leave`, the statutory types, …), so it stays a string.
 */
final readonly class LeaveRequest
{
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?int $userId,
        public ?string $userName,
        public ?string $startDate,
        public ?string $endDate,
        public ?LeaveDurationType $type,
        public ?string $typeLabel,
        public ?string $leaveType,
        public ?string $leaveTypeLabel,
        public ?LeaveStatus $status,
        public float $hours,
        public float $sleeps,
        public ?string $requesterComments,
        public ?string $approverComments,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?bool $canEditDates,
        public ?bool $canCancel,
        public ?bool $canRequestReapproval,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     * @param  array<string, mixed>  $meta  The resource object's own `meta` block.
     */
    public static function fromArray(array $data, array $meta = []): self
    {
        /** @var array{id?: mixed, name?: mixed} $user */
        $user = is_array($data['user'] ?? null) ? $data['user'] : [];
        /** @var array<string, mixed> $can */
        $can = is_array($meta['can'] ?? null) ? $meta['can'] : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            userName: isset($user['name']) ? (string) $user['name'] : null,
            startDate: isset($data['start_date']) ? (string) $data['start_date'] : null,
            endDate: isset($data['end_date']) ? (string) $data['end_date'] : null,
            type: LeaveDurationType::tryFrom((string) ($data['type'] ?? '')),
            typeLabel: isset($data['type_label']) ? (string) $data['type_label'] : null,
            leaveType: isset($data['leave_type']) ? (string) $data['leave_type'] : null,
            leaveTypeLabel: isset($data['leave_type_label']) ? (string) $data['leave_type_label'] : null,
            status: LeaveStatus::tryFrom((string) ($data['status'] ?? '')),
            hours: (float) ($data['hours'] ?? 0),
            sleeps: (float) ($data['sleeps'] ?? 0),
            requesterComments: isset($data['requester_comments']) ? (string) $data['requester_comments'] : null,
            approverComments: isset($data['approver_comments']) ? (string) $data['approver_comments'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            canEditDates: isset($can['edit_dates']) ? (bool) $can['edit_dates'] : null,
            canCancel: isset($can['cancel']) ? (bool) $can['cancel'] : null,
            canRequestReapproval: isset($can['request_reapproval']) ? (bool) $can['request_reapproval'] : null,
        );
    }
}
