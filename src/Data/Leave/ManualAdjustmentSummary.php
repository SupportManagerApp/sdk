<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Hydrator;

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
        #[From('totals.hours_adjustment')]
        public float $totalHoursAdjustment,
        #[From('totals.sleeps_adjustment')]
        public float $totalSleepsAdjustment,
    ) {}

    /**
     * @param  list<ManualAdjustment>  $adjustments
     * @param  array<string, mixed>  $meta  The response `meta` block.
     */
    public static function fromArray(array $adjustments, array $meta): self
    {
        return Hydrator::hydrate(self::class, $meta, ['adjustments' => $adjustments]);
    }
}
