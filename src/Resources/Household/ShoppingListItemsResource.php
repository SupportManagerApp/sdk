<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Household;

use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Data\Household\ShoppingListItem;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * The shared household shopping list.
 *
 * The collection (`query()`, `find()`, `all()`) plus writes — `create()`,
 * `update()`, `destroy()` — and the `togglePurchased()` action that flips an
 * item's needs-purchasing flag. The platform exposes no `show` route, so single
 * items come from the collection rather than a by-id GET.
 *
 * ```php
 * SupportManager::shoppingList()->query()->filter('needs_purchasing', true)->get();
 * SupportManager::shoppingList()->togglePurchased(7);
 * ```
 *
 * @extends Resource<ShoppingListItem>
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
     * Remove an item from the list (`DELETE household/shopping-list/{id}`).
     */
    public function destroy(string|int $id): void
    {
        $this->throwForErrorResponse(parent::delete("{$this->path()}/{$id}"));
    }

    /**
     * Overrides the base `delete()` request helper, and returns its response,
     * only so that v0.2.0 callers of `->delete($id)` keep working.
     *
     * @deprecated Use {@see self::destroy()}, the name every resource gives a DELETE of one record.
     *
     * @param  array<string, mixed>  $data
     */
    public function delete(string|int $id, array $data = []): Response
    {
        $this->deprecated(__FUNCTION__, 'destroy');

        $response = parent::delete("{$this->path()}/{$id}", $data);

        $this->throwForErrorResponse($response);

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
