<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Household;

use SupportManager\Sdk\Data\Household\Consumable;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Household consumables (wipes, gloves, …), each with size variants whose stock
 * any team member can count up or down.
 *
 * The collection (`query()`, `all()`) — filter by `name` (partial), sort by
 * `name` / `created_at` — plus `adjustStock()` on one variant. The platform
 * exposes no `show` route, so single consumables come from the collection.
 *
 * ```php
 * SupportManager::consumables()->query()->filter('name', 'wipes')->get();
 * SupportManager::consumables()->adjustStock($variantId, -1);
 * ```
 *
 * @extends Resource<Consumable>
 */
final class ConsumablesResource extends Resource
{
    /**
     * Add one to (`1`) or take one from (`-1`) a variant's stock, returning the
     * whole consumable so the caller can redraw it. Stock never drops below zero
     * server-side; any other adjustment is a 422
     * ({@see \SupportManager\Sdk\Exceptions\ApiValidationException} on
     * `adjustment`).
     *
     * @param  string|int  $variantId  A {@see \SupportManager\Sdk\Data\Household\ConsumableVariant::$id}.
     * @param  1|-1  $adjustment
     */
    public function adjustStock(string|int $variantId, int $adjustment): ?Consumable
    {
        return $this->item($this->patch("household/consumable-variants/{$variantId}/stock", [
            'adjustment' => $adjustment,
        ]));
    }

    protected function path(): string
    {
        return 'household/consumables';
    }

    protected function hydrate(ResourceObject $object): Consumable
    {
        return Consumable::fromArray($object->flat());
    }
}
