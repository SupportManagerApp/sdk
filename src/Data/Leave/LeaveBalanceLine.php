<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * One axis of a leave balance (hours or sleeping-nights): the entitlement and
 * how much is pending, approved, and remaining.
 *
 * Mirrors one of the `hours`/`sleeps` blocks of the platform's
 * `LeaveBalanceResource`.
 */
final readonly class LeaveBalanceLine
{
    /**
     * @param  array<string, mixed>|null  $percentages
     */
    public function __construct(
        public float $entitlement,
        public float $pending,
        public float $approved,
        public float $remaining,
        public ?array $percentages,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
