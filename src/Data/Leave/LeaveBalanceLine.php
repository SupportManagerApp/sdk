<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

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
        return new self(
            entitlement: (float) ($data['entitlement'] ?? 0),
            pending: (float) ($data['pending'] ?? 0),
            approved: (float) ($data['approved'] ?? 0),
            remaining: (float) ($data['remaining'] ?? 0),
            percentages: is_array($data['percentages'] ?? null) ? $data['percentages'] : null,
        );
    }
}
