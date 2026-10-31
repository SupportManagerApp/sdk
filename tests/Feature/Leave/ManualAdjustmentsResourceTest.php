<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Leave\ManualAdjustment;
use SupportManager\Sdk\Data\Leave\ManualAdjustmentSummary;

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
    fakeApi(['leave/adjustments*' => Http::response(leaveAdjustmentPage(1, 1))]);

    $page = sdk()->leaveAdjustments()->query()->filter('user_id', 3)->get();

    expect($page->items()[0])->toBeInstanceOf(ManualAdjustment::class)
        ->and($page->items()[0]->hoursAdjustment)->toBe(5.0)
        ->and($page->items()[0]->sleepsAdjustment)->toBe(-1.0)
        ->and($page->items()[0]->leaveYear)->toBe(2026);

    Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'filter[user_id]=3'));
});

it('creates an adjustment', function () {
    fakeApi(['leave/adjustments' => Http::response([
        'data' => ['type' => 'manual-adjustments', 'id' => '9', 'attributes' => ['hours_adjustment' => 2.0, 'reason' => 'Bonus']],
    ], 201)]);

    $adjustment = sdk()->leaveAdjustments()->create(['user_id' => 3, 'leave_year' => 2026, 'hours_adjustment' => 2.0, 'sleeps_adjustment' => 0.0, 'reason' => 'Bonus']);

    expect($adjustment->id)->toBe('9')
        ->and($adjustment->reason)->toBe('Bonus');
    Http::assertSent(fn ($r) => $r->method() === 'POST');
});

it('deletes an adjustment', function () {
    fakeApi(['leave/adjustments/9' => ['message' => 'deleted']]);

    sdk()->leaveAdjustments()->destroy(9);

    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), 'leave/adjustments/9'));
});

it('updates an adjustment', function () {
    fakeApi(['leave/adjustments/9' => [
        'data' => ['type' => 'manual-adjustments', 'id' => '9', 'attributes' => ['hours_adjustment' => 3.5, 'reason' => 'Corrected']],
    ]]);

    $adjustment = sdk()->leaveAdjustments()->update(9, ['user_id' => 3, 'leave_year' => 2026, 'hours_adjustment' => 3.5, 'sleeps_adjustment' => 0.0, 'reason' => 'Corrected']);

    expect($adjustment->hoursAdjustment)->toBe(3.5)
        ->and($adjustment->reason)->toBe('Corrected');
    Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_contains($r->url(), 'leave/adjustments/9'));
});

it('lists the current user\'s own adjustments with their totals', function () {
    fakeApi(['leave/adjustments/mine*' => [
        'data' => [
            ['type' => 'manual-adjustments', 'id' => '4', 'attributes' => ['leave_year' => 2025, 'hours_adjustment' => 8.0, 'sleeps_adjustment' => 1.0, 'reason' => 'Carry over']],
            ['type' => 'manual-adjustments', 'id' => '5', 'attributes' => ['leave_year' => 2025, 'hours_adjustment' => -2.0, 'sleeps_adjustment' => 0.0, 'reason' => 'Correction']],
        ],
        'meta' => ['team_id' => 42, 'year' => 2025, 'totals' => ['hours_adjustment' => 6.0, 'sleeps_adjustment' => 1.0]],
    ]]);

    $mine = sdk()->leaveAdjustments()->mine(['year' => 2025]);

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
    fakeApi(['leave/adjustments/mine' => [
        'data' => [],
        'meta' => ['team_id' => 42, 'year' => 2026, 'totals' => ['hours_adjustment' => 0.0, 'sleeps_adjustment' => 0.0]],
    ]]);

    $mine = sdk()->leaveAdjustments()->mine();

    expect($mine->adjustments)->toBe([])
        ->and($mine->year)->toBe(2026)
        ->and($mine->totalHoursAdjustment)->toBe(0.0);
});
