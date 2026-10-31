<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * One standard evening start time offered for a sleepover, checked against the
 * caller's Working Time rest.
 *
 * Mirrors a row of the platform's flat `shift-blocks/{id}/start-options` feed.
 * `time` (`H:i`) is what to send as `start_time` when applying; show a
 * non-`compliant` option disabled, with its `reason`.
 */
final readonly class SleepoverStartOption
{
    public function __construct(
        public string $time,
        public ?string $label,
        public bool $compliant,
        public ?string $reason,
    ) {}

    /**
     * @param  array<string, mixed>  $data  One flat start-option row.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
