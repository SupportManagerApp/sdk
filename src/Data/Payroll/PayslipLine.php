<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Payroll;

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
        public float $total,
        public ?float $hours = null,
        public ?float $rate = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        // Payments expose `total`; total rows expose `amount`.
        $total = $data['total'] ?? $data['amount'] ?? 0;

        return new self(
            description: (string) ($data['description'] ?? ''),
            total: (float) $total,
            hours: isset($data['hours']) ? (float) $data['hours'] : null,
            rate: isset($data['rate']) ? (float) $data['rate'] : null,
        );
    }
}
