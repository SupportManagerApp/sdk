<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Leave\NotWorkingNotice;
use SupportManager\Sdk\Enums\Leave\LeaveDurationType;
use SupportManager\Sdk\Enums\Leave\LeaveStatus;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Resources\Leave\NotWorkingNoticesResource;

function notWorkingNotices(): NotWorkingNoticesResource
{
    return app(NotWorkingNoticesResource::class);
}

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
    Http::fake(['api.test/api/v1/leave/not-working*' => Http::response([
        'data' => [notWorkingNotice('12')],
        'meta' => ['team_id' => 42, 'current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1],
        'links' => ['next' => null],
    ])]);
    bootTenantSession();

    $page = notWorkingNotices()->query()->filter('show', 'past')->get();
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
    Http::fake(['api.test/api/v1/leave/not-working*' => Http::response([
        'data' => [notWorkingNotice('12', ['duration_type' => 'Half Days', 'status' => 'Paused', 'shifts_needing_cover_count' => null])],
    ])]);
    bootTenantSession();

    $notice = notWorkingNotices()->all()->items()[0];

    expect($notice->durationType)->toBeNull()
        ->and($notice->status)->toBeNull()
        ->and($notice->shiftsNeedingCoverCount)->toBeNull();
});

it('records a notice', function () {
    Http::fake(['api.test/api/v1/leave/not-working' => Http::response([
        'data' => notWorkingNotice('13', ['duration_type' => 'Custom Timings', 'note' => null]),
        'meta' => ['team_id' => 42],
    ], 201)]);
    bootTenantSession();

    $notice = notWorkingNotices()->create([
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

it('maps an overlapping notice to a validation exception', function () {
    Http::fake(['api.test/api/v1/leave/not-working' => Http::response([
        'message' => 'invalid',
        'errors' => ['start_date' => ['Another notice already covers part of this time.']],
    ], 422)]);
    bootTenantSession();

    try {
        notWorkingNotices()->create(['duration_type' => 'Whole Days', 'start_date' => '2026-11-09', 'end_date' => '2026-11-10']);
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('start_date'))->toBe('Another notice already covers part of this time.');
    }
});

it('withdraws a notice', function () {
    Http::fake(['api.test/api/v1/leave/not-working/12/withdraw' => Http::response([
        'data' => notWorkingNotice('12', ['status' => 'Cancelled', 'shifts_needing_cover_count' => 1], ['withdraw' => false]),
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    $notice = notWorkingNotices()->withdraw(12);

    expect($notice->status)->toBe(LeaveStatus::Cancelled)
        ->and($notice->canWithdraw)->toBeFalse()
        ->and($notice->shiftsNeedingCoverCount)->toBe(1);

    Http::assertSent(fn ($r) => $r->method() === 'PATCH');
});

it('surfaces a 403 when the notice cannot be withdrawn', function () {
    Http::fake(['api.test/api/v1/leave/not-working/12/withdraw' => Http::response(['message' => 'This action is unauthorized.'], 403)]);
    bootTenantSession();

    expect(fn () => notWorkingNotices()->withdraw(12))->toThrow(SupportManagerApiException::class);
});
