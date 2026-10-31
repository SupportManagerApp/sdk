<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Household;

use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A single shared shopping-list item. Mirrors the platform's
 * `ShoppingListItemResource`; `requestedBy` identifies the user who added it.
 * `section` is the supermarket section (e.g. `dairy_eggs_and_chilled`), null
 * while the server has not sorted it yet — treat unknown values as unsorted.
 */
final readonly class ShoppingListItem
{
    public function __construct(
        public string $id,
        public string $name,
        public ?int $quantity,
        public bool $needsPurchasing,
        public bool $isMaybe,
        public ?string $lastPurchasedOn,
        #[From('requested_by.id')]
        public ?int $requestedById,
        #[From('requested_by.name')]
        public ?string $requestedByName,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?string $section = null,
        public ?string $notes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
