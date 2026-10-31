<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Payroll;

use SupportManager\Sdk\Data\Hydration\Attributes\Can;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * The authenticated employee's payroll starter declaration: their NI number,
 * whether they brought a P45 or filled in the new starter checklist, the P45
 * figures, and the payroll details the declaration sets.
 *
 * Mirrors the platform's `PayrollStarterDeclarationResource`, a flat
 * (non-JSON:API) `data` payload, plus `meta.can.update`. Answers the employee
 * has not given yet are null. The coded fields (`taxFormType`,
 * `starterDeclarationCircumstances`, `finalPayPeriodType`, `studentLoanPlan`,
 * `nationalInsuranceLetter`) are open lists on the platform, so they stay
 * strings. {@see $taxFormUrl} is a link to the stored P45 that expires after
 * five minutes, so do not cache it.
 */
final readonly class StarterDeclaration
{
    public function __construct(
        public ?string $niNumber,
        public ?string $taxFormType,
        public ?string $starterDeclarationCircumstances,
        public ?string $leavingDate,
        public ?float $totalPayToDate,
        public ?float $totalTaxToDate,
        public ?string $finalPayPeriodType,
        public ?int $finalPayPeriodNumber,
        public ?string $taxFormUrl,
        public ?string $nationalInsuranceLetter,
        public ?string $studentLoanPlan,
        public bool $postgraduateLoan,
        public ?string $taxCode,
        public bool $week1Month1,
        public ?string $updatedAt,
        #[Can('update')]
        public bool $canUpdate,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The response `data` block, with the response `meta` under a `meta` key.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
