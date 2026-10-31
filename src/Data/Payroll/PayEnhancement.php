<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Payroll;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A pay enhancement — a named rate multiplier (e.g. time-and-a-half) applied to
 * worked hours. Mirrors the platform's `PayEnhancementResource`.
 *
 * `enhancementFactor` is the raw multiplier value (e.g. `"0.50"`);
 * `enhancementFactorLabel` is its human label (e.g. "Time and a half").
 */
final readonly class PayEnhancement
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public ?string $enhancementFactor,
        public ?string $enhancementFactorLabel,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
