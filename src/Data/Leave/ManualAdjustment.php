<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A manual adjustment to a user's leave balance for a given leave year —
 * hours and/or sleeping-nights, with a reason.
 *
 * Mirrors the platform's `ManualAdjustmentResource` attributes.
 */
final readonly class ManualAdjustment
{
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?int $userId,
        #[From('user.name')]
        public ?string $userName,
        public ?int $leaveYear,
        public float $hoursAdjustment,
        public float $sleepsAdjustment,
        public ?string $reason,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
