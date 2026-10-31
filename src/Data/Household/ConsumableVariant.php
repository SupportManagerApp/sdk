<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Household;

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
        return new self(
            id: (int) ($data['id'] ?? 0),
            size: (string) ($data['size'] ?? ''),
            quantity: (int) ($data['quantity'] ?? 0),
            minimumStock: (int) ($data['minimum_stock'] ?? 0),
            isLowStock: (bool) ($data['is_low_stock'] ?? false),
        );
    }
}
