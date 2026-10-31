<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

use SupportManager\Sdk\Data\Hydration\Attributes\Can;
use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Hydrator;
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
        #[From('user.name')]
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
        #[Can('edit_dates')]
        public ?bool $canEditDates,
        #[Can('cancel')]
        public ?bool $canCancel,
        #[Can('request_reapproval')]
        public ?bool $canRequestReapproval,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     * @param  array<string, mixed>  $meta  The resource object's own `meta` block.
     */
    public static function fromArray(array $data, array $meta = []): self
    {
        return Hydrator::hydrate(self::class, [...$data, 'meta' => $meta]);
    }
}
