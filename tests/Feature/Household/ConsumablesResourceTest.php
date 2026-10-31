<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Household\Consumable;
use SupportManager\Sdk\Data\Household\ConsumableVariant;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Resources\Household\ConsumablesResource;

function householdConsumables(): ConsumablesResource
{
    return app(ConsumablesResource::class);
}

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
    Http::fake(['api.test/api/v1/household/consumables*' => Http::response(householdConsumablePage(1, 1))]);
    bootTenantSession();

    householdConsumables()->query()->filter('name', 'wipes')->sort('-created_at')->perPage(10)->get();

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
    Http::fake(['api.test/api/v1/household/consumables*' => Http::response(householdConsumablePage(1, 2))]);
    bootTenantSession();

    $page = householdConsumables()->query()->get();
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
    Http::fake(['api.test/api/v1/household/consumable-variants/1/stock' => Http::response([
        'data' => householdConsumable('1', ['variants' => [
            ['id' => 1, 'size' => 'Large', 'quantity' => 0, 'minimum_stock' => 2, 'is_low_stock' => true],
        ]]),
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    $consumable = householdConsumables()->adjustStock(1, -1);

    expect($consumable)->toBeInstanceOf(Consumable::class)
        ->and($consumable->variants[0]->quantity)->toBe(0);
    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/api/v1/household/consumable-variants/1/stock')
        && $request->hasHeader('X-Team-ID', '42')
        && $request['adjustment'] === -1);
});

it('maps a 422 on an invalid adjustment to a validation exception', function () {
    Http::fake(['api.test/api/v1/household/consumable-variants/1/stock' => Http::response([
        'message' => 'The selected adjustment is invalid.',
        'errors' => ['adjustment' => ['The selected adjustment is invalid.']],
    ], 422)]);
    bootTenantSession();

    try {
        householdConsumables()->adjustStock(1, 5);
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('adjustment'))->toBe('The selected adjustment is invalid.');
    }
});

it('throws when the household module is switched off (404)', function () {
    Http::fake(['api.test/api/v1/household/consumable-variants/1/stock' => Http::response(['message' => 'Not found'], 404)]);
    bootTenantSession();

    householdConsumables()->adjustStock(1, 1);
})->throws(SupportManagerApiException::class);
