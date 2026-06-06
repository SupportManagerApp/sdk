<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Household;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Data\Household\ShoppingListItem;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * The shared household shopping list.
 *
 * The collection (`query()`, `find()`, `all()`) plus writes — `create()`,
 * `update()`, `delete()` — and the `togglePurchased()` action that flips an
 * item's needs-purchasing flag. The platform exposes no `show` route, so single
 * items come from the collection rather than a by-id GET.
 *
 * ```php
 * SupportManager::shoppingList()->query()->filter('needs_purchasing', true)->get();
 * SupportManager::shoppingList()->togglePurchased(7);
 * ```
 *
 * @extends resource<ShoppingListItem>
 */
final class ShoppingListItemsResource extends Resource
{
    /**
     * Add an item to the shopping list.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ?ShoppingListItem
    {
        return $this->item($this->post('household/shopping-list', $data));
    }

    /**
     * Update an existing item.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string|int $id, array $data): ?ShoppingListItem
    {
        return $this->item($this->patch("household/shopping-list/{$id}", $data));
    }

    /**
     * Remove an item from the list. The base helper's signature is preserved for
     * LSP, but callers pass just the id: `->delete(5)`.
     *
     * @param  array<string, mixed>  $data
     */
    public function delete(string|int $id, array $data = []): Response
    {
        $uri = "household/shopping-list/{$id}";

        $response = $this->tenant(fn (PendingRequest $request) => $request->delete($uri, $data));

        $this->throwForErrorResponse($response, $uri);

        return $response;
    }

    /**
     * Toggle whether an item still needs purchasing, returning the updated item.
     */
    public function togglePurchased(string|int $id): ?ShoppingListItem
    {
        return $this->item($this->patch("household/shopping-list/{$id}/toggle-purchased"));
    }

    protected function path(): string
    {
        return 'household/shopping-list';
    }

    protected function hydrate(ResourceObject $object): ShoppingListItem
    {
        return ShoppingListItem::fromArray($object->flat());
    }
}
