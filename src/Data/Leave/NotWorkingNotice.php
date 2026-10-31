<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

use SupportManager\Sdk\Data\Hydration\Attributes\Can;
use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Hydrator;
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
        #[From('user.name')]
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
        #[Can('withdraw')]
        public ?bool $canWithdraw,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
