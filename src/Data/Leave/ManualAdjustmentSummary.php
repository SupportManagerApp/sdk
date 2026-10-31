<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

/**
 * The authenticated user's own manual leave adjustments for a leave year,
 * oldest first, with their totals.
 *
 * Mirrors `GET leave/adjustments/mine`: the adjustments come from the JSON:API
 * `data` list and the year and totals from the response `meta`.
 */
final readonly class ManualAdjustmentSummary
{
    /**
     * @param  list<ManualAdjustment>  $adjustments
     */
    public function __construct(
        public ?int $year,
        public array $adjustments,
        public float $totalHoursAdjustment,
        public float $totalSleepsAdjustment,
    ) {}

    /**
     * @param  list<ManualAdjustment>  $adjustments
     * @param  array<string, mixed>  $meta  The response `meta` block.
     */
    public static function fromArray(array $adjustments, array $meta): self
    {
        /** @var array<string, mixed> $totals */
        $totals = is_array($meta['totals'] ?? null) ? $meta['totals'] : [];

        return new self(
            year: isset($meta['year']) ? (int) $meta['year'] : null,
            adjustments: $adjustments,
            totalHoursAdjustment: (float) ($totals['hours_adjustment'] ?? 0),
            totalSleepsAdjustment: (float) ($totals['sleeps_adjustment'] ?? 0),
        );
    }
}
