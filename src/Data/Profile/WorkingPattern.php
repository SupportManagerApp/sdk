<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Profile;

/**
 * The user's current working pattern, nested in {@see EmploymentSummary}.
 */
final readonly class WorkingPattern
{
    public function __construct(
        public bool $isBankContract,
        public float $weeklyAverageHours,
        public float $weeklySleepingNights,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The `working_pattern` object.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isBankContract: (bool) ($data['is_bank_contract'] ?? false),
            weeklyAverageHours: (float) ($data['weekly_average_hours'] ?? 0),
            weeklySleepingNights: (float) ($data['weekly_sleeping_nights'] ?? 0),
        );
    }
}
