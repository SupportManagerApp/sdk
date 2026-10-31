<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Payroll;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * One ad-hoc deduction row — a payroll correction or an attachment/earnings
 * order — as a description and the amount taken.
 *
 * Mirrors a single entry of the `corrections` / `earnings_orders` arrays the
 * platform's `EstimatedPayResource` emits inside its `deductions` block.
 */
final readonly class DeductionLine
{
    public function __construct(
        public string $description,
        public float $amount,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
