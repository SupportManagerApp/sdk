<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Payroll;

use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * An estimated-pay projection for a single month. Mirrors the platform's
 * `EstimatedPayResource`.
 *
 * The platform returns this as a flat (non-JSON:API) `data` payload. The figures
 * are split between the worked-hours/sleeps totals, any enhancement rows, the
 * grand total, and — when a full net-pay estimate is available — the
 * gross/net/deductions breakdown (otherwise those fields are null).
 *
 * `hours` and `sleeps` carry only their totals; the per-type rows the
 * platform sends with them (`hours.breakdown`, `sleeps.breakdown`) are
 * {@see self::$hoursBreakdown} and {@see self::$sleepsBreakdown}.
 *
 * @phpstan-type Money array{total_value: float, total_cost: float}
 */
final readonly class EstimatedPay
{
    /**
     * @param  Money  $hours
     * @param  Money  $sleeps
     * @param  list<array{type: string, total_value: float, total_cost: float}>  $enhancements
     * @param  Money  $grandTotal
     * @param  list<EstimatedPayBreakdownLine>  $hoursBreakdown  `hours.breakdown`: the hours total by entry type.
     * @param  list<EstimatedPayBreakdownLine>  $sleepsBreakdown  `sleeps.breakdown`: the sleeping-nights total by entry type.
     */
    public function __construct(
        public int $year,
        public int $month,
        public ?string $monthLabel,
        public array $hours,
        public array $sleeps,
        public array $enhancements,
        public array $grandTotal,
        public ?float $grossPay,
        public ?float $netPay,
        public ?float $deductionsTotal,
        public ?EstimatedPayDeductions $deductions,
        #[From('hours.breakdown')]
        #[ListOf(EstimatedPayBreakdownLine::class)]
        public array $hoursBreakdown = [],
        #[From('sleeps.breakdown')]
        #[ListOf(EstimatedPayBreakdownLine::class)]
        public array $sleepsBreakdown = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flat (non-JSON:API) estimated-pay payload.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data, [
            'hours' => self::money($data['hours'] ?? null),
            'sleeps' => self::money($data['sleeps'] ?? null),
            'enhancements' => self::enhancements($data['enhancements'] ?? null),
            'grandTotal' => self::money($data['grand_total'] ?? null),
        ]);
    }

    /**
     * @return array{total_value: float, total_cost: float}
     */
    private static function money(mixed $value): array
    {
        $value = is_array($value) ? $value : [];

        return [
            'total_value' => (float) ($value['total_value'] ?? 0),
            'total_cost' => (float) ($value['total_cost'] ?? 0),
        ];
    }

    /**
     * @return list<array{type: string, total_value: float, total_cost: float}>
     */
    private static function enhancements(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(static function (mixed $row): array {
            $row = is_array($row) ? $row : [];

            return [
                'type' => (string) ($row['type'] ?? ''),
                'total_value' => (float) ($row['total_value'] ?? 0),
                'total_cost' => (float) ($row['total_cost'] ?? 0),
            ];
        }, $value));
    }
}
