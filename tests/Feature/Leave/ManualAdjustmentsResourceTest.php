<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Leave\ManualAdjustment;
use SupportManager\Sdk\Data\Leave\ManualAdjustmentSummary;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Resources\Leave\ManualAdjustmentsResource;

function leaveAdjustments(): ManualAdjustmentsResource
{
    return app(ManualAdjustmentsResource::class);
}

function leaveAdjustmentPage(int $page, int $lastPage): array
{
    return [
        'data' => [[
            'type' => 'manual-adjustments',
            'id' => (string) $page,
            'attributes' => [
                'user_id' => 3,
                'leave_year' => 2026,
                'hours_adjustment' => 5.0,
                'sleeps_adjustment' => -1.0,
                'reason' => "Reason {$page}",
            ],
        ]],
        'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => 1, 'total' => $lastPage],
        'links' => ['next' => $page < $lastPage ? 'https://api.test/api/v1/leave/adjustments?page='.($page + 1) : null],
    ];
}

it('queries adjustments with filters and hydrates DTOs', function () {
    Http::fake(['api.test/api/v1/leave/adjustments*' => Http::response(leaveAdjustmentPage(1, 1))]);
    bootTenantSession();

    $page = leaveAdjustments()->query()->filter('user_id', 3)->get();

    expect($page->items()[0])->toBeInstanceOf(ManualAdjustment::class)
        ->and($page->items()[0]->hoursAdjustment)->toBe(5.0)
        ->and($page->items()[0]->sleepsAdjustment)->toBe(-1.0)
        ->and($page->items()[0]->leaveYear)->toBe(2026);

    Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'filter[user_id]=3'));
});

it('creates an adjustment', function () {
    Http::fake(['api.test/api/v1/leave/adjustments' => Http::response([
        'data' => ['type' => 'manual-adjustments', 'id' => '9', 'attributes' => ['hours_adjustment' => 2.0, 'reason' => 'Bonus']],
    ], 201)]);
    bootTenantSession();

    $adjustment = leaveAdjustments()->create(['user_id' => 3, 'leave_year' => 2026, 'hours_adjustment' => 2.0, 'reason' => 'Bonus']);

    expect($adjustment->id)->toBe('9')
        ->and($adjustment->reason)->toBe('Bonus');
    Http::assertSent(fn ($r) => $r->method() === 'POST');
});

it('deletes an adjustment', function () {
    Http::fake(['api.test/api/v1/leave/adjustments/9' => Http::response(['message' => 'deleted'])]);
    bootTenantSession();

    leaveAdjustments()->destroy(9);

    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), 'leave/adjustments/9'));
});

it('maps a 422 to a validation exception on create', function () {
    Http::fake(['api.test/api/v1/leave/adjustments' => Http::response([
        'message' => 'invalid',
        'errors' => ['user_id' => ['The user id is required.']],
    ], 422)]);
    bootTenantSession();

    try {
        leaveAdjustments()->create([]);
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('user_id'))->toBe('The user id is required.');
    }
});

it('updates an adjustment', function () {
    Http::fake(['api.test/api/v1/leave/adjustments/9' => Http::response([
        'data' => ['type' => 'manual-adjustments', 'id' => '9', 'attributes' => ['hours_adjustment' => 3.5, 'reason' => 'Corrected']],
    ])]);
    bootTenantSession();

    $adjustment = leaveAdjustments()->update(9, ['hours_adjustment' => 3.5, 'reason' => 'Corrected']);

    expect($adjustment->hoursAdjustment)->toBe(3.5)
        ->and($adjustment->reason)->toBe('Corrected');
    Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_contains($r->url(), 'leave/adjustments/9'));
});

it('lists the current user\'s own adjustments with their totals', function () {
    Http::fake(['api.test/api/v1/leave/adjustments/mine*' => Http::response([
        'data' => [
            ['type' => 'manual-adjustments', 'id' => '4', 'attributes' => ['leave_year' => 2025, 'hours_adjustment' => 8.0, 'sleeps_adjustment' => 1.0, 'reason' => 'Carry over']],
            ['type' => 'manual-adjustments', 'id' => '5', 'attributes' => ['leave_year' => 2025, 'hours_adjustment' => -2.0, 'sleeps_adjustment' => 0.0, 'reason' => 'Correction']],
        ],
        'meta' => ['team_id' => 42, 'year' => 2025, 'totals' => ['hours_adjustment' => 6.0, 'sleeps_adjustment' => 1.0]],
    ])]);
    bootTenantSession();

    $mine = leaveAdjustments()->mine(['year' => 2025]);

    expect($mine)->toBeInstanceOf(ManualAdjustmentSummary::class)
        ->and($mine->year)->toBe(2025)
        ->and($mine->adjustments)->toHaveCount(2)
        ->and($mine->adjustments[0])->toBeInstanceOf(ManualAdjustment::class)
        ->and($mine->adjustments[1]->reason)->toBe('Correction')
        ->and($mine->totalHoursAdjustment)->toBe(6.0)
        ->and($mine->totalSleepsAdjustment)->toBe(1.0);

    Http::assertSent(fn ($r) => $r->method() === 'GET' && str_contains($r->url(), 'leave/adjustments/mine?year=2025') && $r->hasHeader('X-Team-ID', '42'));
});

it('returns an empty summary when the user has no adjustments', function () {
    Http::fake(['api.test/api/v1/leave/adjustments/mine' => Http::response([
        'data' => [],
        'meta' => ['team_id' => 42, 'year' => 2026, 'totals' => ['hours_adjustment' => 0.0, 'sleeps_adjustment' => 0.0]],
    ])]);
    bootTenantSession();

    $mine = leaveAdjustments()->mine();

    expect($mine->adjustments)->toBe([])
        ->and($mine->year)->toBe(2026)
        ->and($mine->totalHoursAdjustment)->toBe(0.0);
});
