<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Payroll;

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
        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
            enhancementFactor: isset($data['enhancement_factor']) ? (string) $data['enhancement_factor'] : null,
            enhancementFactorLabel: isset($data['enhancement_factor_label']) ? (string) $data['enhancement_factor_label'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}
