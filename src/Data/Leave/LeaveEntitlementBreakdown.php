<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

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
        return new self(
            contract: self::source($data['contract'] ?? null),
            overtime: self::source($data['overtime'] ?? null),
            toil: self::source($data['toil'] ?? null),
            adjustments: self::source($data['adjustments'] ?? null),
            total: self::source($data['total'] ?? null),
        );
    }

    private static function source(mixed $value): LeaveEntitlementSource
    {
        /** @var array<string, mixed> $source */
        $source = is_array($value) ? $value : [];

        return LeaveEntitlementSource::fromArray($source);
    }
}
