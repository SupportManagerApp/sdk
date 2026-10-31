<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Household\Chore;
use SupportManager\Sdk\Enums\Household\ChoreFrequency;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Resources\Household\ChoresResource;

function householdChores(): ChoresResource
{
    return app(ChoresResource::class);
}

function householdChore(string $id, array $attributes = []): array
{
    return [
        'type' => 'chores',
        'id' => $id,
        'attributes' => array_merge([
            'team_id' => 42,
            'name' => "Chore {$id}",
            'description' => 'Do the thing',
            'frequency' => 'Weekly',
            'frequency_label' => 'Weekly',
            'custom_frequency_days' => null,
            'last_completed' => '2026-06-01',
            'snoozed_until' => null,
            'is_snoozed' => false,
            'completed_by' => ['id' => 3, 'name' => 'Grace'],
            'days_since_completion' => 5,
            'is_overdue' => false,
            'should_highlight' => false,
            'created_at' => '2026-05-01T10:00:00Z',
            'updated_at' => '2026-06-01T10:00:00Z',
        ], $attributes),
    ];
}

function householdChorePage(int $page, int $lastPage): array
{
    return [
        'data' => [householdChore((string) $page)],
        'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => 1, 'total' => $lastPage],
        'links' => ['next' => $page < $lastPage ? 'https://api.test/api/v1/household/chores?page='.($page + 1) : null],
    ];
}

it('encodes Spatie-style query params on chores', function () {
    Http::fake(['api.test/api/v1/household/chores*' => Http::response(householdChorePage(1, 1))]);
    bootTenantSession();

    householdChores()->query()
        ->filter('due', true)
        ->sort('-last_completed')
        ->perPage(10)
        ->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[due]=1')
            && str_contains($url, 'sort=-last_completed')
            && str_contains($url, 'per_page=10')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates chores with the typed frequency enum and pagination', function () {
    Http::fake(['api.test/api/v1/household/chores*' => Http::response(householdChorePage(1, 2))]);
    bootTenantSession();

    $page = householdChores()->query()->get();

    expect($page->items()[0])->toBeInstanceOf(Chore::class)
        ->and($page->items()[0]->frequency)->toBe(ChoreFrequency::Weekly)
        ->and($page->items()[0]->completedByName)->toBe('Grace')
        ->and($page->items()[0]->daysSinceCompletion)->toBe(5)
        ->and($page->hasMorePages())->toBeTrue();
});

it('finds a chore by id', function () {
    Http::fake(['api.test/api/v1/household/chores/7*' => Http::response(['data' => householdChore('7')])]);
    bootTenantSession();

    expect(householdChores()->find(7)->id)->toBe('7');
});

it('returns null when finding a missing chore (404)', function () {
    Http::fake(['api.test/api/v1/household/chores/999*' => Http::response(['message' => 'Not found'], 404)]);
    bootTenantSession();

    expect(householdChores()->find(999))->toBeNull();
});

it('creates a chore', function () {
    Http::fake(['api.test/api/v1/household/chores' => Http::response(['data' => householdChore('5', ['name' => 'Vacuum'])], 201)]);
    bootTenantSession();

    expect(householdChores()->create(['name' => 'Vacuum', 'frequency' => 'Weekly'])->name)->toBe('Vacuum');
    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['name'] === 'Vacuum');
});

it('updates a chore', function () {
    Http::fake(['api.test/api/v1/household/chores/5' => Http::response(['data' => householdChore('5', ['name' => 'Mop'])])]);
    bootTenantSession();

    expect(householdChores()->update(5, ['name' => 'Mop'])->name)->toBe('Mop');
    Http::assertSent(fn ($request) => $request->method() === 'PATCH');
});

it('deletes a chore', function () {
    Http::fake(['api.test/api/v1/household/chores/5' => Http::response(['message' => 'Chore deleted successfully.'])]);
    bootTenantSession();

    householdChores()->destroy(5);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), 'household/chores/5'));
});

it('marks a chore complete', function () {
    Http::fake(['api.test/api/v1/household/chores/5/complete' => Http::response(['data' => householdChore('5', ['last_completed' => '2026-06-06'])])]);
    bootTenantSession();

    expect(householdChores()->complete(5)->lastCompleted)->toBe('2026-06-06');
    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), '/complete'));
});

it('snoozes a chore until a date', function () {
    Http::fake(['api.test/api/v1/household/chores/5/snooze' => Http::response(['data' => householdChore('5', ['snoozed_until' => '2026-06-20', 'is_snoozed' => true])])]);
    bootTenantSession();

    $chore = householdChores()->snooze(5, '2026-06-20');

    expect($chore->isSnoozed)->toBeTrue()
        ->and($chore->snoozedUntil)->toBe('2026-06-20');
    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), '/snooze')
        && $request['snoozed_until'] === '2026-06-20');
});

it('maps a 422 to a validation exception', function () {
    Http::fake(['api.test/api/v1/household/chores' => Http::response([
        'message' => 'The given data was invalid',
        'errors' => ['name' => ['The name field is required.']],
    ], 422)]);
    bootTenantSession();

    try {
        householdChores()->create([]);
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('name'))->toBe('The name field is required.');
    }
});

it('hydrates the wave 2 chore fields and meta.can flags', function () {
    Http::fake(['api.test/api/v1/household/chores*' => Http::response([
        'data' => [array_merge(householdChore('5', [
            'name' => '  Water Plants ',
            'normalized_name' => 'water plants',
            'category' => ['id' => 2, 'name' => 'Garden', 'color' => '#22c55e', 'icon' => 'heroicon-o-sun'],
            'snoozed_until' => '2026-06-07',
            'is_snoozed' => true,
            'snoozed_at' => '2026-06-06',
            'snoozed_by' => ['id' => 4, 'name' => 'Ada'],
            'planned_for' => null,
        ]), ['meta' => ['can' => ['update' => true, 'delete' => false]]])],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1],
    ])]);
    bootTenantSession();

    $chore = householdChores()->query()->get()->items()[0];

    expect($chore->normalizedName)->toBe('water plants')
        ->and($chore->categoryId)->toBe(2)
        ->and($chore->categoryName)->toBe('Garden')
        ->and($chore->categoryColor)->toBe('#22c55e')
        ->and($chore->categoryIcon)->toBe('heroicon-o-sun')
        ->and($chore->snoozedAt)->toBe('2026-06-06')
        ->and($chore->snoozedById)->toBe(4)
        ->and($chore->snoozedByName)->toBe('Ada')
        ->and($chore->plannedFor)->toBeNull()
        ->and($chore->canUpdate)->toBeTrue()
        ->and($chore->canDelete)->toBeFalse();
});

it('decodes a chore without a category or permissions', function () {
    Http::fake(['api.test/api/v1/household/chores*' => Http::response([
        'data' => [householdChore('5', ['category' => null])],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1],
    ])]);
    bootTenantSession();

    $chore = householdChores()->query()->get()->items()[0];

    expect($chore->categoryId)->toBeNull()
        ->and($chore->categoryName)->toBeNull()
        ->and($chore->canUpdate)->toBeFalse()
        ->and($chore->canDelete)->toBeFalse();
});

it('completes several chores by id', function () {
    Http::fake(['api.test/api/v1/household/chores/complete' => Http::response([
        'data' => [householdChore('1', ['days_since_completion' => 0]), householdChore('2', ['days_since_completion' => 0])],
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    $chores = householdChores()->completeMany([1, '2']);

    expect($chores)->toHaveCount(2)
        ->and($chores[0])->toBeInstanceOf(Chore::class)
        ->and($chores[1]->id)->toBe('2')
        ->and($chores[1]->daysSinceCompletion)->toBe(0);
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/household/chores/complete')
        && $request->hasHeader('X-Team-ID', '42')
        && $request['chore_ids'] === [1, 2]
        && ! isset($request['group']));
});

it('completes a chore group by name', function () {
    Http::fake(['api.test/api/v1/household/chores/complete' => Http::response([
        'data' => [householdChore('3')],
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    expect(householdChores()->completeGroup('Water plants'))->toHaveCount(1);
    Http::assertSent(fn ($request) => $request['group'] === 'Water plants' && ! isset($request['chore_ids']));
});

it('marks several chores not needed today', function () {
    Http::fake(['api.test/api/v1/household/chores/not-needed' => Http::response([
        'data' => [householdChore('1', ['snoozed_until' => '2026-06-07', 'is_snoozed' => true])],
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    $chores = householdChores()->markNotNeeded([1]);

    expect($chores[0]->isSnoozed)->toBeTrue()
        ->and($chores[0]->snoozedUntil)->toBe('2026-06-07');
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/household/chores/not-needed')
        && $request['chore_ids'] === [1]);
});

it('marks a chore group not needed today', function () {
    Http::fake(['api.test/api/v1/household/chores/not-needed' => Http::response(['data' => [], 'meta' => ['team_id' => 42]])]);
    bootTenantSession();

    householdChores()->markGroupNotNeeded('Water plants');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/household/chores/not-needed')
        && $request['group'] === 'Water plants');
});

it('maps a 422 on a bulk selection to a validation exception', function () {
    Http::fake(['api.test/api/v1/household/chores/complete' => Http::response([
        'message' => 'There are no chores in that group.',
        'errors' => ['group' => ['There are no chores in that group.']],
    ], 422)]);
    bootTenantSession();

    try {
        householdChores()->completeGroup('Nothing');
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('group'))->toBe('There are no chores in that group.');
    }
});

it('throws when a bulk selection is refused (403)', function () {
    Http::fake(['api.test/api/v1/household/chores/not-needed' => Http::response(['message' => 'This action is unauthorized.'], 403)]);
    bootTenantSession();

    householdChores()->markNotNeeded([1]);
})->throws(SupportManagerApiException::class);

it('throws when a non-privileged user snoozes beyond tomorrow (403)', function () {
    Http::fake(['api.test/api/v1/household/chores/5/snooze' => Http::response(['message' => 'This action is unauthorized.'], 403)]);
    bootTenantSession();

    householdChores()->snooze(5, '2026-07-01');
})->throws(SupportManagerApiException::class);
