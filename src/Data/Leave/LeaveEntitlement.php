<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * An annual leave entitlement linked to a working pattern — the hours and
 * sleeping-nights allocated over an effective date range.
 *
 * Mirrors the platform's `LeaveEntitlementResource` attributes.
 */
final readonly class LeaveEntitlement
{
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?int $userId,
        #[From('user.name')]
        public ?string $userName,
        public ?int $workingPatternId,
        #[From('working_pattern.name')]
        public ?string $workingPatternName,
        public ?string $effectiveFrom,
        public ?string $effectiveUntil,
        public float $hoursHoliday,
        public float $sleepingNightsHoliday,
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
