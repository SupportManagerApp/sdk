<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Household;

use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;
use SupportManager\Sdk\Data\Hydration\Hydrator;

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
        #[ListOf(ConsumableVariant::class)]
        public array $variants,
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
