<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Payroll;

use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A single line on a payslip — a payment, a deduction, or a total row.
 *
 * The platform splits these across four resources (payments, deductions,
 * period totals, YTD totals) but they share the same shape: a description and
 * an amount, with payments additionally carrying `hours` and `rate`. The
 * SDK folds them into one value object; the rate/hours fields are null on a
 * deduction or total row.
 */
final readonly class PayslipLine
{
    public function __construct(
        public string $description,
        // Payments expose `total`; total rows expose `amount`.
        #[From('total', 'amount')]
        public float $total,
        public ?float $hours = null,
        public ?float $rate = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
