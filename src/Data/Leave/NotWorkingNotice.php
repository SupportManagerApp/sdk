<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

use SupportManager\Sdk\Enums\Leave\LeaveDurationType;
use SupportManager\Sdk\Enums\Leave\LeaveStatus;

/**
 * Notice from a self-employed contractor that they will not be working.
 *
 * Mirrors the platform's `NotWorkingNoticeResource` attributes, plus the
 * viewer's `meta.can.withdraw` permission. Nothing needs approving: a notice
 * stands as `Approved` straight away and becomes `Cancelled` once withdrawn.
 * {@see $endDate} is when the notice ends (08:00 the day after
 * {@see $lastDayOff} for `Whole Days`). {@see $shiftsNeedingCoverCount} is the
 * released shifts nobody has picked up yet, and is null when not counted.
 */
final readonly class NotWorkingNotice
{
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?int $userId,
        public ?string $userName,
        public ?string $startDate,
        public ?string $endDate,
        public ?string $lastDayOff,
        public ?LeaveDurationType $durationType,
        public ?LeaveStatus $status,
        public ?string $note,
        public bool $givenAtShortNotice,
        public ?int $shiftsNeedingCoverCount,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?bool $canWithdraw,
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
            lastDayOff: isset($data['last_day_off']) ? (string) $data['last_day_off'] : null,
            durationType: LeaveDurationType::tryFrom((string) ($data['duration_type'] ?? '')),
            status: LeaveStatus::tryFrom((string) ($data['status'] ?? '')),
            note: isset($data['note']) ? (string) $data['note'] : null,
            givenAtShortNotice: (bool) ($data['given_at_short_notice'] ?? false),
            shiftsNeedingCoverCount: isset($data['shifts_needing_cover_count']) ? (int) $data['shifts_needing_cover_count'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            canWithdraw: isset($can['withdraw']) ? (bool) $can['withdraw'] : null,
        );
    }
}
