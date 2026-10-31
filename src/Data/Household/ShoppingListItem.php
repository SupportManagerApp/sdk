<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Household;

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
        public ?int $requestedById,
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
        /** @var array{id?: mixed, name?: mixed} $requestedBy */
        $requestedBy = is_array($data['requested_by'] ?? null) ? $data['requested_by'] : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            quantity: isset($data['quantity']) ? (int) $data['quantity'] : null,
            needsPurchasing: (bool) ($data['needs_purchasing'] ?? false),
            isMaybe: (bool) ($data['is_maybe'] ?? false),
            lastPurchasedOn: isset($data['last_purchased_on']) ? (string) $data['last_purchased_on'] : null,
            requestedById: isset($requestedBy['id']) ? (int) $requestedBy['id'] : null,
            requestedByName: isset($requestedBy['name']) ? (string) $requestedBy['name'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            section: isset($data['section']) ? (string) $data['section'] : null,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
        );
    }
}
