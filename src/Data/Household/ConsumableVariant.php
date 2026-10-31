<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Household;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * One size of a {@see Consumable} and its stock level. Mirrors an entry of the
 * platform's `ConsumableResource` `variants` list; `isLowStock` is the server's
 * view (a minimum stock above zero, and a quantity below it).
 */
final readonly class ConsumableVariant
{
    public function __construct(
        public int $id,
        public string $size,
        public int $quantity,
        public int $minimumStock,
        public bool $isLowStock,
    ) {}

    /**
     * @param  array<string, mixed>  $data  One `variants` entry.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
