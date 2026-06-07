<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Payroll;

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
        public array $corrections,
        public array $earningsOrders,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The `deductions` block.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            incomeTax: (float) ($data['income_tax'] ?? 0),
            nationalInsurance: (float) ($data['national_insurance'] ?? 0),
            pension: (float) ($data['pension'] ?? 0),
            studentLoan: (float) ($data['student_loan'] ?? 0),
            postgraduateLoan: (float) ($data['postgraduate_loan'] ?? 0),
            corrections: self::lines($data['corrections'] ?? null),
            earningsOrders: self::lines($data['earnings_orders'] ?? null),
        );
    }

    /**
     * @return list<DeductionLine>
     */
    private static function lines(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $row): DeductionLine => DeductionLine::fromArray(is_array($row) ? $row : []),
            $value,
        ));
    }
}
