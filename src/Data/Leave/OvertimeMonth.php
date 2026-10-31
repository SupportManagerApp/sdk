<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

/**
 * One logged month of overtime: the hours and sleeps worked over contract, and
 * the leave (TOIL) accrued from them.
 *
 * Mirrors one entry of the `months` list in `GET leave/overtime`.
 */
final readonly class OvertimeMonth
{
    public function __construct(
        public ?int $id,
        public ?string $monthEndDate,
        public float $hoursWorked,
        public float $hoursAccrued,
        public float $sleepsWorked,
        public float $sleepsAccrued,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            monthEndDate: isset($data['month_end_date']) ? (string) $data['month_end_date'] : null,
            hoursWorked: (float) ($data['hours_worked'] ?? 0),
            hoursAccrued: (float) ($data['hours_accrued'] ?? 0),
            sleepsWorked: (float) ($data['sleeps_worked'] ?? 0),
            sleepsAccrued: (float) ($data['sleeps_accrued'] ?? 0),
        );
    }
}
