<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Leave\NotWorkingNotice;
use SupportManager\Sdk\Enums\Leave\LeaveDurationType;
use SupportManager\Sdk\Enums\Leave\LeaveStatus;

/**
 * @param  array<string, mixed>  $attributes
 * @param  array<string, mixed>  $can
 */
function notWorkingNotice(string $id, array $attributes = [], array $can = ['withdraw' => true]): array
{
    return [
        'type' => 'not-working-notices',
        'id' => $id,
        'attributes' => [
            'team_id' => 42,
            'user_id' => 9,
            'user' => ['id' => 9, 'name' => 'Sam Carter'],
            'start_date' => '2026-11-09T08:00:00+00:00',
            'end_date' => '2026-11-12T08:00:00+00:00',
            'last_day_off' => '2026-11-11',
            'duration_type' => 'Whole Days',
            'status' => 'Approved',
            'note' => 'Away at a family wedding',
            'given_at_short_notice' => false,
            'shifts_needing_cover_count' => 2,
            'created_at' => '2026-10-01T09:00:00.000000Z',
            'updated_at' => '2026-10-01T09:00:00.000000Z',
            ...$attributes,
        ],
        'meta' => ['can' => $can],
    ];
}

it('lists notices with the show filter and hydrates DTOs', function () {
    fakeApi(['leave/not-working*' => [
        'data' => [notWorkingNotice('12')],
        'meta' => ['team_id' => 42, 'current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1],
        'links' => ['next' => null],
    ]]);

    $page = sdk()->leaveNotWorkingNotices()->query()->filter('show', 'past')->get();
    $notice = $page->items()[0];

    expect($notice)->toBeInstanceOf(NotWorkingNotice::class)
        ->and($notice->id)->toBe('12')
        ->and($notice->userId)->toBe(9)
        ->and($notice->userName)->toBe('Sam Carter')
        ->and($notice->startDate)->toBe('2026-11-09T08:00:00+00:00')
        ->and($notice->endDate)->toBe('2026-11-12T08:00:00+00:00')
        ->and($notice->lastDayOff)->toBe('2026-11-11')
        ->and($notice->durationType)->toBe(LeaveDurationType::WholeDays)
        ->and($notice->status)->toBe(LeaveStatus::Approved)
        ->and($notice->note)->toBe('Away at a family wedding')
        ->and($notice->givenAtShortNotice)->toBeFalse()
        ->and($notice->shiftsNeedingCoverCount)->toBe(2)
        ->and($notice->canWithdraw)->toBeTrue()
        ->and($page->total())->toBe(1);

    Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'filter[show]=past') && $r->hasHeader('X-Team-ID', '42'));
});

it('ignores a duration type or status it does not recognise', function () {
    $notice = notWorkingNotice('12', ['duration_type' => 'Half Days', 'status' => 'Paused']);
    // whenCounted() leaves the count out entirely when it was not loaded.
    unset($notice['attributes']['shifts_needing_cover_count']);

    fakeApi(['leave/not-working*' => ['data' => [$notice]]]);

    $notice = sdk()->leaveNotWorkingNotices()->all()->items()[0];

    expect($notice->durationType)->toBeNull()
        ->and($notice->status)->toBeNull()
        ->and($notice->shiftsNeedingCoverCount)->toBeNull();
});

it('records a notice', function () {
    fakeApi(['leave/not-working' => Http::response([
        'data' => notWorkingNotice('13', ['duration_type' => 'Custom Timings', 'note' => null]),
        'meta' => ['team_id' => 42],
    ], 201)]);

    $notice = sdk()->leaveNotWorkingNotices()->create([
        'contractor_id' => 9,
        'duration_type' => 'Custom Timings',
        'start_date' => '2026-11-09',
        'start_time' => '13:00',
        'end_time' => '17:30',
    ]);

    expect($notice)->toBeInstanceOf(NotWorkingNotice::class)
        ->and($notice->id)->toBe('13')
        ->and($notice->durationType)->toBe(LeaveDurationType::CustomTimings)
        ->and($notice->note)->toBeNull();

    Http::assertSent(fn ($r) => $r->method() === 'POST'
        && $r['contractor_id'] === 9
        && $r['start_time'] === '13:00');
});

it('withdraws a notice', function () {
    fakeApi(['leave/not-working/12/withdraw' => [
        'data' => notWorkingNotice('12', ['status' => 'Cancelled', 'shifts_needing_cover_count' => 1], ['withdraw' => false]),
        'meta' => ['team_id' => 42],
    ]]);

    $notice = sdk()->leaveNotWorkingNotices()->withdraw(12);

    expect($notice->status)->toBe(LeaveStatus::Cancelled)
        ->and($notice->canWithdraw)->toBeFalse()
        ->and($notice->shiftsNeedingCoverCount)->toBe(1);

    Http::assertSent(fn ($r) => $r->method() === 'PATCH');
});
