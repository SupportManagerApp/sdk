<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Accidents;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * How an accident was reported to the HSE under RIDDOR, from an accident
 * report's `riddor` attribute. Only admins and managers receive it, and
 * recording it is web-only, so every field may still be null.
 */
final readonly class AccidentRiddorOutcome
{
    public function __construct(
        public ?string $howReported,
        public ?string $declaredName,
        public ?string $declaredAt,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data  The report's `riddor` attribute.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
