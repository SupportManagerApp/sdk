<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Accidents;

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
        return new self(
            howReported: isset($data['how_reported']) ? (string) $data['how_reported'] : null,
            declaredName: isset($data['declared_name']) ? (string) $data['declared_name'] : null,
            declaredAt: isset($data['declared_at']) ? (string) $data['declared_at'] : null,
        );
    }
}
