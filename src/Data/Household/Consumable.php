<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Household;

/**
 * A household consumable (wipes, gloves, …) with its size variants and their
 * stock levels. Mirrors the platform's `ConsumableResource` attributes.
 */
final readonly class Consumable
{
    /**
     * @param  list<ConsumableVariant>  $variants
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $variants,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        $variants = is_array($data['variants'] ?? null) ? $data['variants'] : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            variants: array_values(array_map(
                ConsumableVariant::fromArray(...),
                array_filter($variants, is_array(...)),
            )),
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}
