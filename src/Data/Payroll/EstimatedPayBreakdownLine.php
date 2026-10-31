<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Payroll;

use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * One payroll entry type inside an estimated-pay total, e.g. the "Contracted
 * Hours" part of {@see EstimatedPay::$hoursBreakdown} or the "Waking Night"
 * part of {@see EstimatedPay::$sleepsBreakdown}.
 *
 * Mirrors a row of the platform's `tooltip_breakdown`
 * (`PayrollReportManager::addTotalRows()`), sent as `hours.breakdown` and
 * `sleeps.breakdown` by `EstimatedPayResource`. The unit count arrives as
 * `total_payroll_value`; this calls it `totalValue`, as the totals do.
 *
 * When some of the row was worked after the previous month's payroll closed,
 * the platform also splits it into the late part (`afterClose*`) and this
 * month's part (`currentMonth*`). Otherwise those four are null.
 */
final readonly class EstimatedPayBreakdownLine
{
    public function __construct(
        public string $type,
        #[From('total_payroll_value')]
        public float $totalValue,
        public float $totalCost,
        #[From('after_close_payroll_value')]
        public ?float $afterCloseValue = null,
        public ?float $afterCloseCost = null,
        #[From('current_month_payroll_value')]
        public ?float $currentMonthValue = null,
        public ?float $currentMonthCost = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  One `breakdown` row.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
