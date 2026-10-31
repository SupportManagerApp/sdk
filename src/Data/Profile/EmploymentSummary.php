<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Profile;

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
        return new self(
            id: (string) ($data['id'] ?? ''),
            startDate: isset($data['start_date']) ? (string) $data['start_date'] : null,
            leavingDate: isset($data['leaving_date']) ? (string) $data['leaving_date'] : null,
            employmentLength: (string) ($data['employment_length'] ?? ''),
            contractType: (string) ($data['contract_type'] ?? ''),
            currentRateOfPay: is_array($data['current_rate_of_pay'] ?? null) ? RateOfPay::fromArray($data['current_rate_of_pay']) : null,
            workingPattern: is_array($data['working_pattern'] ?? null) ? WorkingPattern::fromArray($data['working_pattern']) : null,
            contractedAnnualSalary: isset($data['contracted_annual_salary']) ? (float) $data['contracted_annual_salary'] : null,
        );
    }
}
