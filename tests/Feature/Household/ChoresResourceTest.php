<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Household\Chore;
use SupportManager\Sdk\Enums\Household\ChoreFrequency;
use SupportManager\Sdk\Exceptions\ApiValidationException;
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
        ->filter('overdue', true)
        ->sort('-last_completed')
        ->perPage(10)
        ->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[overdue]=1')
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

    householdChores()->delete(5);

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
