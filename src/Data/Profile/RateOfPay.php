<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Profile;

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
        return new self(
            hourlyRate: (float) ($data['hourly_rate'] ?? 0),
            sleepingNightRate: (float) ($data['sleeping_night_rate'] ?? 0),
            startDate: isset($data['start_date']) ? (string) $data['start_date'] : null,
        );
    }
}
