<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Profile;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * The figures on the Employment tab of the web profile, as returned by
 * `GET user/employment`.
 *
 * Mirrors the platform's `EmploymentSummaryResource` (type
 * `employment-summaries`; the id is the user's). Dates are null when the user
 * has no employment record, and `employmentLength` is then "N/A".
 * `contractType` is a label such as `Permanent Contracted Employee` or
 * `Bank Worker`, kept as a string because the platform treats the list as open.
 * `contractedAnnualSalary` is null for bank workers and when there is no
 * working pattern or rate of pay.
 */
final readonly class EmploymentSummary
{
    public function __construct(
        public string $id,
        public ?string $startDate,
        public ?string $leavingDate,
        public string $employmentLength,
        public string $contractType,
        public ?RateOfPay $currentRateOfPay,
        public ?WorkingPattern $workingPattern,
        public ?float $contractedAnnualSalary,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
