<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Household\ShoppingListItem;

function householdShoppingItem(string $id, array $attributes = []): array
{
    return [
        'type' => 'shopping-list',
        'id' => $id,
        'attributes' => array_merge([
            'name' => "Item {$id}",
            'quantity' => 2,
            'needs_purchasing' => true,
            'is_maybe' => false,
            'last_purchased_on' => null,
            'requested_by' => ['id' => 9, 'name' => 'Ada'],
        ], $attributes),
    ];
}

function householdShoppingPage(int $page, int $lastPage): array
{
    return [
        'data' => [householdShoppingItem((string) $page)],
        'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => 1, 'total' => $lastPage],
        'links' => ['next' => $page < $lastPage ? 'https://api.test/api/v1/household/shopping-list?page='.($page + 1) : null],
    ];
}

it('encodes Spatie-style query params on the shopping list', function () {
    fakeApi(['household/shopping-list*' => Http::response(householdShoppingPage(1, 1))]);

    sdk()->shoppingList()->query()
        ->filter('needs_purchasing', true)
        ->sort('-created_at')
        ->perPage(20)
        ->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[needs_purchasing]=1')
            && str_contains($url, 'sort=-created_at')
            && str_contains($url, 'per_page=20')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates shopping list items with pagination metadata', function () {
    fakeApi(['household/shopping-list*' => Http::response(householdShoppingPage(1, 3))]);

    $page = sdk()->shoppingList()->query()->get();

    expect($page->items())->toHaveCount(1)
        ->and($page->items()[0])->toBeInstanceOf(ShoppingListItem::class)
        ->and($page->items()[0]->name)->toBe('Item 1')
        ->and($page->items()[0]->requestedByName)->toBe('Ada')
        ->and($page->lastPage())->toBe(3)
        ->and($page->hasMorePages())->toBeTrue();
});

it('finds nothing via the collection since there is no show route', function () {
    fakeApi(['household/shopping-list/9*' => ['data' => householdShoppingItem('9')]]);

    expect(sdk()->shoppingList()->find(9)->id)->toBe('9');
});

it('returns null when finding a missing item (404)', function () {
    fakeApi(['household/shopping-list/999*' => Http::response(['message' => 'Not found'], 404)]);

    expect(sdk()->shoppingList()->find(999))->toBeNull();
});

it('creates a shopping list item', function () {
    fakeApi(['household/shopping-list' => Http::response(['data' => householdShoppingItem('5', ['name' => 'Milk'])], 201)]);

    $item = sdk()->shoppingList()->create(['name' => 'Milk', 'quantity' => 1]);

    expect($item->name)->toBe('Milk');
    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['name'] === 'Milk');
});

it('updates a shopping list item', function () {
    fakeApi(['household/shopping-list/5' => ['data' => householdShoppingItem('5', ['name' => 'Bread'])]]);

    expect(sdk()->shoppingList()->update(5, ['name' => 'Bread'])->name)->toBe('Bread');
    Http::assertSent(fn ($request) => $request->method() === 'PATCH');
});

it('deletes a shopping list item', function () {
    fakeApi(['household/shopping-list/5' => ['message' => 'Shopping list item deleted successfully.']]);

    sdk()->shoppingList()->destroy(5);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), 'household/shopping-list/5'));
});

it('toggles the purchased flag', function () {
    fakeApi(['household/shopping-list/5/toggle-purchased' => ['data' => householdShoppingItem('5', ['needs_purchasing' => false])]]);

    expect(sdk()->shoppingList()->togglePurchased(5)->needsPurchasing)->toBeFalse();
    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), 'toggle-purchased'));
});

it('hydrates the section and notes and filters by purchased today', function () {
    fakeApi(['household/shopping-list*' => [
        'data' => [householdShoppingItem('1', ['section' => 'dairy_eggs_and_chilled', 'notes' => 'The blue Lurpak, 500g'])],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1],
    ]]);

    $item = sdk()->shoppingList()->query()->filter('purchased_today', true)->get()->items()[0];

    expect($item->section)->toBe('dairy_eggs_and_chilled')
        ->and($item->notes)->toBe('The blue Lurpak, 500g');
    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'filter[purchased_today]=1'));
});
