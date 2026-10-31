<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Household\Consumable;
use SupportManager\Sdk\Data\Household\ConsumableVariant;

function householdConsumable(string $id, array $attributes = []): array
{
    return [
        'type' => 'consumables',
        'id' => $id,
        'attributes' => array_merge([
            'name' => "Consumable {$id}",
            'variants' => [
                ['id' => 1, 'size' => 'Large', 'quantity' => 1, 'minimum_stock' => 2, 'is_low_stock' => true],
                ['id' => 2, 'size' => 'Small', 'quantity' => 4, 'minimum_stock' => 0, 'is_low_stock' => false],
            ],
            'created_at' => '2026-05-01T10:00:00Z',
            'updated_at' => '2026-06-01T10:00:00Z',
        ], $attributes),
    ];
}

function householdConsumablePage(int $page, int $lastPage): array
{
    return [
        'data' => [householdConsumable((string) $page)],
        'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => 1, 'total' => $lastPage, 'team_id' => 42],
        'links' => ['next' => $page < $lastPage ? 'https://api.test/api/v1/household/consumables?page='.($page + 1) : null],
    ];
}

it('encodes Spatie-style query params on consumables', function () {
    fakeApi(['household/consumables*' => Http::response(householdConsumablePage(1, 1))]);

    sdk()->consumables()->query()->filter('name', 'wipes')->sort('-created_at')->perPage(10)->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'household/consumables')
            && str_contains($url, 'filter[name]=wipes')
            && str_contains($url, 'sort=-created_at')
            && str_contains($url, 'per_page=10')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates consumables with typed variants and pagination', function () {
    fakeApi(['household/consumables*' => Http::response(householdConsumablePage(1, 2))]);

    $page = sdk()->consumables()->query()->get();
    $consumable = $page->items()[0];

    expect($consumable)->toBeInstanceOf(Consumable::class)
        ->and($consumable->id)->toBe('1')
        ->and($consumable->name)->toBe('Consumable 1')
        ->and($consumable->variants)->toHaveCount(2)
        ->and($consumable->variants[0])->toBeInstanceOf(ConsumableVariant::class)
        ->and($consumable->variants[0]->id)->toBe(1)
        ->and($consumable->variants[0]->size)->toBe('Large')
        ->and($consumable->variants[0]->quantity)->toBe(1)
        ->and($consumable->variants[0]->minimumStock)->toBe(2)
        ->and($consumable->variants[0]->isLowStock)->toBeTrue()
        ->and($consumable->variants[1]->isLowStock)->toBeFalse()
        ->and($page->hasMorePages())->toBeTrue();
});

it('adjusts a variant\'s stock and returns the whole consumable', function () {
    fakeApi(['household/consumable-variants/1/stock' => [
        'data' => householdConsumable('1', ['variants' => [
            ['id' => 1, 'size' => 'Large', 'quantity' => 0, 'minimum_stock' => 2, 'is_low_stock' => true],
        ]]),
        'meta' => ['team_id' => 42],
    ]]);

    $consumable = sdk()->consumables()->adjustStock(1, -1);

    expect($consumable)->toBeInstanceOf(Consumable::class)
        ->and($consumable->variants[0]->quantity)->toBe(0);
    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/api/v1/household/consumable-variants/1/stock')
        && $request->hasHeader('X-Team-ID', '42')
        && $request['adjustment'] === -1);
});
