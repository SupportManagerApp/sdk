<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Household\ChoreCategory;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Resources\Household\ChoreCategoriesResource;

function householdChoreCategories(): ChoreCategoriesResource
{
    return app(ChoreCategoriesResource::class);
}

function householdChoreCategory(string $id, array $attributes = []): array
{
    return [
        'type' => 'chore-categories',
        'id' => $id,
        'attributes' => array_merge([
            'name' => "Category {$id}",
            'color' => '#ff0000',
            'icon' => 'broom',
            'chores_count' => 4,
            'created_at' => '2026-05-01T10:00:00Z',
            'updated_at' => '2026-06-01T10:00:00Z',
        ], $attributes),
    ];
}

it('encodes Spatie-style query params on chore categories', function () {
    Http::fake(['api.test/api/v1/household/chore-categories*' => Http::response([
        'data' => [householdChoreCategory('1')],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1],
        'links' => ['next' => null],
    ])]);
    bootTenantSession();

    householdChoreCategories()->query()->filter('name', 'Kitchen')->sort('name')->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[name]=Kitchen')
            && str_contains($url, 'sort=name')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates chore categories with pagination metadata', function () {
    Http::fake(['api.test/api/v1/household/chore-categories*' => Http::response([
        'data' => [householdChoreCategory('1'), householdChoreCategory('2', ['name' => 'Garden'])],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 2],
        'links' => ['next' => null],
    ])]);
    bootTenantSession();

    $page = householdChoreCategories()->all();

    expect($page->items())->toHaveCount(2)
        ->and($page->items()[0])->toBeInstanceOf(ChoreCategory::class)
        ->and($page->items()[0]->choresCount)->toBe(4)
        ->and($page->items()[1]->name)->toBe('Garden')
        ->and($page->total())->toBe(2);
});

it('maps a 422 on chore categories to a validation exception', function () {
    // The platform's only 422 here is the invalid-team response: a bare message, no field errors.
    Http::fake(['api.test/api/v1/household/chore-categories*' => Http::response([
        'message' => 'Invalid or missing team. Please specify a valid team ID via X-Team-ID header or team_id parameter.',
    ], 422)]);
    bootTenantSession();

    try {
        householdChoreCategories()->query()->filter('color', '#3b82f6')->get();
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->getMessage())->toContain('Invalid or missing team')
            ->and($e->errors)->toBe([]);
    }
});

it('hydrates the due chores count', function () {
    Http::fake(['api.test/api/v1/household/chore-categories*' => Http::response([
        'data' => [householdChoreCategory('1', ['due_chores_count' => 2])],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1],
    ])]);
    bootTenantSession();

    $category = householdChoreCategories()->all()->items()[0];

    expect($category->choresCount)->toBe(4)
        ->and($category->dueChoresCount)->toBe(2);
});
