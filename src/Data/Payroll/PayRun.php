<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Payroll;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A pay run — a month's worth of payslips rolled up into one summary row.
 * Mirrors the platform's `PayRunResource`.
 *
 * The platform exposes these as a flat (non-JSON:API) `data` payload keyed by
 * {@see $monthKey} (`YYYY-MM`); there is no surrogate id. `status` is derived
 * server-side from the published/total counts: `draft`, `partial`, or
 * `published`.
 */
final readonly class PayRun
{
    public function __construct(
        public string $monthKey,
        public ?string $monthLabel,
        public ?string $processDate,
        public int $payslipCount,
        public int $publishedCount,
        public string $status,
        public float $totalGross,
        public float $totalNet,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flat (non-JSON:API) pay-run row.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
