<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * One source of a leave entitlement (the contract, overtime, time off in lieu
 * or manual adjustments, or their total), in hours and sleeping-nights.
 *
 * Mirrors one block of the balance `breakdown`. {@see $hasData} says whether
 * anything was recorded from this source in the year; it is null for the
 * contract and total blocks, which the platform sends without it.
 */
final readonly class LeaveEntitlementSource
{
    public function __construct(
        public float $hours,
        public float $sleeps,
        public ?bool $hasData,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
