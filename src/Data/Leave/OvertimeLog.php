<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

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
        public array $months,
        public OvertimeMonth $totals,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The response `data` block.
     */
    public static function fromArray(array $data): self
    {
        $months = is_array($data['months'] ?? null) ? $data['months'] : [];
        /** @var array<string, mixed> $totals */
        $totals = is_array($data['totals'] ?? null) ? $data['totals'] : [];

        return new self(
            year: isset($data['year']) ? (int) $data['year'] : null,
            months: array_values(array_map(
                static fn (mixed $month): OvertimeMonth => OvertimeMonth::fromArray(is_array($month) ? $month : []),
                $months,
            )),
            totals: OvertimeMonth::fromArray($totals),
        );
    }
}
