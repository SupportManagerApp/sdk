<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * The current team's leave year as of today, as carried on `GET user`
 * (`current_leave_year`).
 *
 * `year` is the calendar year the leave year starts in, and `label` its
 * display form (`2026/27` for a team whose year starts in April). `startsOn`
 * and `endsOn` are `YYYY-MM-DD` dates. To browse other years, or to see which
 * are locked, use {@see \SupportManager\Sdk\Resources\Leave\LeaveBalanceResource::availableYears()}.
 */
final readonly class CurrentLeaveYear
{
    public function __construct(
        public int $year,
        public string $label,
        public string $startsOn,
        public string $endsOn,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
