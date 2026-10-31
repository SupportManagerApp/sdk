<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

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
        /** @var array<string, mixed> $hours */
        $hours = is_array($data['hours'] ?? null) ? $data['hours'] : [];
        /** @var array<string, mixed> $sleeps */
        $sleeps = is_array($data['sleeps'] ?? null) ? $data['sleeps'] : [];

        return new self(
            year: isset($data['year']) ? (int) $data['year'] : null,
            hours: LeaveBalanceLine::fromArray($hours),
            sleeps: LeaveBalanceLine::fromArray($sleeps),
            breakdown: is_array($data['breakdown'] ?? null) ? LeaveEntitlementBreakdown::fromArray($data['breakdown']) : null,
        );
    }
}
