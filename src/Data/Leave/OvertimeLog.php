<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * The authenticated user's overtime (TOIL) log for a leave year: one row per
 * logged month, in date order, with totals across the year.
 *
 * Mirrors the platform's `GET leave/overtime` payload, a plain `{data: {year,
 * months, totals}}` envelope rather than a JSON:API document. {@see $totals}
 * reuses {@see OvertimeMonth} with no `id` or `month_end_date`.
 */
final readonly class OvertimeLog
{
    /**
     * @param  list<OvertimeMonth>  $months
     */
    public function __construct(
        public ?int $year,
        #[ListOf(OvertimeMonth::class)]
        public array $months,
        public OvertimeMonth $totals,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The response `data` block.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
