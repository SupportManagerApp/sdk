<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake shopping-list items.
 *
 * Generic CRUD (list, find, create, update, delete) comes for free from the
 * resource path; this factory adds the {@see togglePurchased()} action that flips
 * an item's needs-purchasing flag, returning the updated item.
 *
 * ```php
 * SupportManager::fake()->seed(ShoppingListItemFactory::new()->count(3));
 * SupportManager::shoppingList()->togglePurchased(1);
 * ```
 */
final class ShoppingListItemFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'household/shopping-list';
    }

    public function type(): string
    {
        return 'shopping-list';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'name' => "Item {$number}",
            'quantity' => 2,
            'needs_purchasing' => true,
            'is_maybe' => false,
            'section' => 'dairy_eggs_and_chilled',
            'notes' => null,
            'last_purchased_on' => null,
            'requested_by' => ['id' => 9, 'name' => 'Ada'],
            'created_at' => '2026-01-01T09:00:00Z',
            'updated_at' => '2026-01-01T10:00:00Z',
        ];
    }

    /**
     * Mark the item as already purchased (no longer needing purchasing).
     */
    public function purchased(): static
    {
        return $this->state([
            'needs_purchasing' => false,
            'last_purchased_on' => '2026-01-01',
        ]);
    }

    /**
     * Mark the item as a "maybe" rather than a firm requirement.
     */
    public function maybe(): static
    {
        return $this->state(['is_maybe' => true]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::patch('household/shopping-list/{id}/toggle-purchased', static function (RecordStore $store, array $matches): mixed {
                $record = $store->find('household/shopping-list', $matches[1]);

                if ($record === null) {
                    return Http::response(['message' => 'Not found'], 404);
                }

                $toggled = $store->update('household/shopping-list', $matches[1], [
                    'needs_purchasing' => ! ($record['needs_purchasing'] ?? false),
                ]);

                return Http::response(Json::item('shopping-list', $toggled ?? $record));
            }),
        ];
    }
}
