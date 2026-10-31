<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * Where a leave entitlement comes from — the contract, overtime, time off in
 * lieu and manual adjustments — and their total, as the leave dashboard shows
 * them.
 *
 * Mirrors the `breakdown` block `GET leave/balance?include=breakdown` adds to
 * the balance.
 */
final readonly class LeaveEntitlementBreakdown
{
    public function __construct(
        public LeaveEntitlementSource $contract,
        public LeaveEntitlementSource $overtime,
        public LeaveEntitlementSource $toil,
        public LeaveEntitlementSource $adjustments,
        public LeaveEntitlementSource $total,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The balance's `breakdown` block.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
