<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Profile;

use SupportManager\Sdk\Data\Hydration\Hydrator;

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
        return Hydrator::hydrate(self::class, $data);
    }
}
