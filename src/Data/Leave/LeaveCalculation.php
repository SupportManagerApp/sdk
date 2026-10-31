<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * The balance impact of a prospective leave request: how much leave it needs,
 * the user's balance for the leave year it falls in, and what would remain
 * after it.
 *
 * Mirrors the platform's `leave/balance/calculate` response, a plain
 * `{data: {requirement, current_balance, balance_after_request, year}}` body.
 * A negative {@see $balanceAfterRequest} means the request would overdraw the
 * balance.
 */
final readonly class LeaveCalculation
{
    public function __construct(
        public ?int $year,
        public LeaveAmount $requirement,
        public LeaveBalance $currentBalance,
        public LeaveAmount $balanceAfterRequest,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The response `data` block.
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $current */
        $current = is_array($data['current_balance'] ?? null) ? $data['current_balance'] : [];

        // The balance block carries no year of its own; it is the calculation's.
        return Hydrator::hydrate(self::class, $data, [
            'currentBalance' => LeaveBalance::fromArray([...$current, 'year' => $data['year'] ?? null]),
        ]);
    }
}
