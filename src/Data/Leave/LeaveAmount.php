<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * An amount of leave on both axes — hours and sleeping-nights — such as what a
 * prospective request needs, or what would be left after it.
 */
final readonly class LeaveAmount
{
    public function __construct(
        public float $hours,
        public float $sleeps,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
