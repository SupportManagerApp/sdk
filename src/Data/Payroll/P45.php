<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Payroll;

use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A leaver's P45 document. Mirrors the platform's `P45Resource`.
 *
 * Unlike a P60 there are no side-loaded detail rows: collection and `show()`
 * responses carry the same flat attributes. {@see $thisEmploymentPay} and
 * {@see $thisEmploymentTax} are null unless the form shows this employment
 * separately from the year-to-date totals.
 */
final readonly class P45
{
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?int $employeeId,
        #[From('employee.name')]
        public ?string $employeeName,
        public ?string $taxYear,
        public ?string $leavingDate,
        public ?string $taxCode,
        public bool $week1Month1,
        public ?int $monthNumber,
        public bool $studentLoanDeductionsContinue,
        public float $totalPayToDate,
        public float $totalTaxToDate,
        public ?float $thisEmploymentPay,
        public ?float $thisEmploymentTax,
        public ?string $publishedAt,
        public bool $viewed,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
