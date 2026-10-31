<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Payroll;

use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * The deductions breakdown of an estimated-pay projection — the figures that
 * bridge gross to net.
 *
 * Mirrors the `deductions` block of the platform's `EstimatedPayResource`
 * (sourced from `NetPayEstimator`): the statutory lines (income tax, NI,
 * pension, student/postgraduate loan) plus any ad-hoc {@see DeductionLine}
 * corrections and earnings orders. Present only when a full net-pay estimate is
 * available; otherwise {@see EstimatedPay::$deductions} is null.
 */
final readonly class EstimatedPayDeductions
{
    /**
     * @param  list<DeductionLine>  $corrections
     * @param  list<DeductionLine>  $earningsOrders
     */
    public function __construct(
        public float $incomeTax,
        public float $nationalInsurance,
        public float $pension,
        public float $studentLoan,
        public float $postgraduateLoan,
        #[ListOf(DeductionLine::class)]
        public array $corrections,
        #[ListOf(DeductionLine::class)]
        public array $earningsOrders,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The `deductions` block.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
