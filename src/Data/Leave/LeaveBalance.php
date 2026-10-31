<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A user's leave balance for a leave year, split into hours and
 * sleeping-nights axes.
 *
 * Mirrors the platform's `LeaveBalanceResource`. This endpoint is not a
 * JSON:API resource object — it has no `id`/`type` — so it is hydrated straight
 * from the response `data` block rather than via the base `item()` helper.
 * {@see $breakdown} is present only when the balance was fetched with
 * `include=breakdown`, and null otherwise.
 */
final readonly class LeaveBalance
{
    public function __construct(
        public ?int $year,
        public LeaveBalanceLine $hours,
        public LeaveBalanceLine $sleeps,
        public ?LeaveEntitlementBreakdown $breakdown,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The response `data` block.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
