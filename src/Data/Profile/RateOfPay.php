<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Profile;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * The user's current rate of pay, nested in {@see EmploymentSummary}.
 */
final readonly class RateOfPay
{
    public function __construct(
        public float $hourlyRate,
        public float $sleepingNightRate,
        public ?string $startDate,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The `current_rate_of_pay` object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
