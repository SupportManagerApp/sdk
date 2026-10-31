<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

use SupportManager\Sdk\Data\Hydration\Hydrator;

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
        return Hydrator::hydrate(self::class, $data);
    }
}
