<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Household\ChoreCategory;

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
    fakeApi(['household/chore-categories*' => [
        'data' => [householdChoreCategory('1')],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1],
        'links' => ['next' => null],
    ]]);

    sdk()->choreCategories()->query()->filter('name', 'Kitchen')->sort('name')->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[name]=Kitchen')
            && str_contains($url, 'sort=name')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates chore categories with pagination metadata', function () {
    fakeApi(['household/chore-categories*' => [
        'data' => [householdChoreCategory('1'), householdChoreCategory('2', ['name' => 'Garden'])],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 2],
        'links' => ['next' => null],
    ]]);

    $page = sdk()->choreCategories()->all();

    expect($page->items())->toHaveCount(2)
        ->and($page->items()[0])->toBeInstanceOf(ChoreCategory::class)
        ->and($page->items()[0]->choresCount)->toBe(4)
        ->and($page->items()[1]->name)->toBe('Garden')
        ->and($page->total())->toBe(2);
});

it('hydrates the due chores count', function () {
    fakeApi(['household/chore-categories*' => [
        'data' => [householdChoreCategory('1', ['due_chores_count' => 2])],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1],
    ]]);

    $category = sdk()->choreCategories()->all()->items()[0];

    expect($category->choresCount)->toBe(4)
        ->and($category->dueChoresCount)->toBe(2);
});
