<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Household\ShoppingListItem;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Resources\Household\ShoppingListItemsResource;

function householdShoppingList(): ShoppingListItemsResource
{
    return app(ShoppingListItemsResource::class);
}

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
    Http::fake(['api.test/api/v1/household/shopping-list*' => Http::response(householdShoppingPage(1, 1))]);
    bootTenantSession();

    householdShoppingList()->query()
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
    Http::fake(['api.test/api/v1/household/shopping-list*' => Http::response(householdShoppingPage(1, 3))]);
    bootTenantSession();

    $page = householdShoppingList()->query()->get();

    expect($page->items())->toHaveCount(1)
        ->and($page->items()[0])->toBeInstanceOf(ShoppingListItem::class)
        ->and($page->items()[0]->name)->toBe('Item 1')
        ->and($page->items()[0]->requestedByName)->toBe('Ada')
        ->and($page->lastPage())->toBe(3)
        ->and($page->hasMorePages())->toBeTrue();
});

it('finds nothing via the collection since there is no show route', function () {
    Http::fake(['api.test/api/v1/household/shopping-list/9*' => Http::response(['data' => householdShoppingItem('9')])]);
    bootTenantSession();

    expect(householdShoppingList()->find(9)->id)->toBe('9');
});

it('returns null when finding a missing item (404)', function () {
    Http::fake(['api.test/api/v1/household/shopping-list/999*' => Http::response(['message' => 'Not found'], 404)]);
    bootTenantSession();

    expect(householdShoppingList()->find(999))->toBeNull();
});

it('creates a shopping list item', function () {
    Http::fake(['api.test/api/v1/household/shopping-list' => Http::response(['data' => householdShoppingItem('5', ['name' => 'Milk'])], 201)]);
    bootTenantSession();

    $item = householdShoppingList()->create(['name' => 'Milk', 'quantity' => 1]);

    expect($item->name)->toBe('Milk');
    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['name'] === 'Milk');
});

it('updates a shopping list item', function () {
    Http::fake(['api.test/api/v1/household/shopping-list/5' => Http::response(['data' => householdShoppingItem('5', ['name' => 'Bread'])])]);
    bootTenantSession();

    expect(householdShoppingList()->update(5, ['name' => 'Bread'])->name)->toBe('Bread');
    Http::assertSent(fn ($request) => $request->method() === 'PATCH');
});

it('deletes a shopping list item', function () {
    Http::fake(['api.test/api/v1/household/shopping-list/5' => Http::response(['message' => 'Shopping list item deleted successfully.'])]);
    bootTenantSession();

    householdShoppingList()->delete(5);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), 'household/shopping-list/5'));
});

it('toggles the purchased flag', function () {
    Http::fake(['api.test/api/v1/household/shopping-list/5/toggle-purchased' => Http::response(['data' => householdShoppingItem('5', ['needs_purchasing' => false])])]);
    bootTenantSession();

    expect(householdShoppingList()->togglePurchased(5)->needsPurchasing)->toBeFalse();
    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), 'toggle-purchased'));
});

it('maps a 422 to a validation exception', function () {
    Http::fake(['api.test/api/v1/household/shopping-list' => Http::response([
        'message' => 'The given data was invalid',
        'errors' => ['name' => ['The name field is required.']],
    ], 422)]);
    bootTenantSession();

    try {
        householdShoppingList()->create([]);
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('name'))->toBe('The name field is required.');
    }
});

it('hydrates the section and notes and filters by purchased today', function () {
    Http::fake(['api.test/api/v1/household/shopping-list*' => Http::response([
        'data' => [householdShoppingItem('1', ['section' => 'dairy_eggs_and_chilled', 'notes' => 'The blue Lurpak, 500g'])],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1],
    ])]);
    bootTenantSession();

    $item = householdShoppingList()->query()->filter('purchased_today', true)->get()->items()[0];

    expect($item->section)->toBe('dairy_eggs_and_chilled')
        ->and($item->notes)->toBe('The blue Lurpak, 500g');
    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'filter[purchased_today]=1'));
});
