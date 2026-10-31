<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Rota\Shift;
use SupportManager\Sdk\Data\Rota\ShiftAmendmentRequest;
use SupportManager\Sdk\Data\Rota\ShiftApplication;
use SupportManager\Sdk\Enums\Rota\EntryType;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Resources\Rota\ShiftsResource;

function rotaShifts(): ShiftsResource
{
    return app(ShiftsResource::class);
}

function rotaShiftPage(int $page, int $lastPage): array
{
    return [
        'data' => [[
            'type' => 'rota-entries',
            'id' => (string) $page,
            'attributes' => [
                'type' => 'Contracted Hours',
                'type_label' => 'Contracted Hours',
                'start' => '2026-06-06T09:00:00Z',
                'end' => '2026-06-06T17:00:00Z',
                'duration' => 8.0,
                'is_published' => true,
                'is_available' => false,
                'user' => ['id' => 7, 'name' => "User {$page}", 'avatar' => 'http://a/avatar.png'],
            ],
        ]],
        'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => 1, 'total' => $lastPage],
        'links' => ['next' => $page < $lastPage ? 'https://api.test/api/v1/rota/shifts?page='.($page + 1) : null],
    ];
}

it('encodes Spatie-style query params and tenant header', function () {
    Http::fake(['api.test/api/v1/rota/shifts*' => Http::response(rotaShiftPage(1, 1))]);
    bootTenantSession();

    rotaShifts()->query()
        ->filter('mode', 'available')
        ->sort('start')
        ->perPage(25)
        ->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[mode]=available')
            && str_contains($url, 'sort=start')
            && str_contains($url, 'per_page=25')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates shifts with pagination metadata', function () {
    Http::fake(['api.test/api/v1/rota/shifts*' => Http::response(rotaShiftPage(1, 3))]);
    bootTenantSession();

    $page = rotaShifts()->query()->get();

    expect($page->items())->toHaveCount(1)
        ->and($page->items()[0])->toBeInstanceOf(Shift::class)
        ->and($page->items()[0]->type)->toBe(EntryType::ContractedHours)
        ->and($page->items()[0]->userName)->toBe('User 1')
        ->and($page->items()[0]->userId)->toBe(7)
        ->and($page->lastPage())->toBe(3)
        ->and($page->hasMorePages())->toBeTrue();
});

it('finds a single shift by id', function () {
    Http::fake([
        'api.test/api/v1/rota/shifts/5' => Http::response([
            'data' => ['type' => 'rota-entries', 'id' => '5', 'attributes' => ['type' => 'Sickness', 'duration' => 0]],
        ]),
    ]);
    bootTenantSession();

    $shift = rotaShifts()->find(5);

    expect($shift)->toBeInstanceOf(Shift::class)
        ->and($shift->id)->toBe('5')
        ->and($shift->type)->toBe(EntryType::Sickness);
});

it('returns null when finding a missing shift (404)', function () {
    Http::fake(['api.test/api/v1/rota/shifts/999' => Http::response(['message' => 'Not found'], 404)]);
    bootTenantSession();

    expect(rotaShifts()->find(999))->toBeNull();
});

it('lists todays shifts via the collection helper', function () {
    Http::fake(['api.test/api/v1/rota/shifts/today' => Http::response([
        'data' => [
            ['type' => 'rota-entries', 'id' => '1', 'attributes' => ['type' => 'Contracted Hours', 'duration' => 8]],
            ['type' => 'rota-entries', 'id' => '2', 'attributes' => ['type' => 'Overtime Hours', 'duration' => 4]],
        ],
    ])]);
    bootTenantSession();

    $shifts = rotaShifts()->today();

    expect($shifts)->toHaveCount(2)
        ->and($shifts[0])->toBeInstanceOf(Shift::class)
        ->and($shifts[1]->type)->toBe(EntryType::OvertimeHours);
});

it('lists available shifts', function () {
    Http::fake(['api.test/api/v1/rota/shifts/available' => Http::response([
        'data' => [['type' => 'rota-entries', 'id' => '3', 'attributes' => ['is_available' => true, 'duration' => 8]]],
    ])]);
    bootTenantSession();

    $shifts = rotaShifts()->available();

    expect($shifts)->toHaveCount(1)
        ->and($shifts[0]->isAvailable)->toBeTrue();
});

it('applies for a shift and hydrates the created applications', function () {
    Http::fake(['api.test/api/v1/rota/shifts/3/apply' => Http::response([
        'data' => [[
            'type' => 'shift-applications',
            'id' => '88',
            'attributes' => ['status' => 'Pending', 'user' => ['id' => 7, 'name' => 'Ada']],
        ]],
        'message' => 'Applied',
    ], 201)]);
    bootTenantSession();

    $applications = rotaShifts()->apply(3, ['is_split_application' => false]);

    expect($applications)->toHaveCount(1)
        ->and($applications[0])->toBeInstanceOf(ShiftApplication::class)
        ->and($applications[0]->status)->toBe('Pending');
    Http::assertSent(fn ($request) => $request['is_split_application'] === false);
});

it('maps a 422 on apply to a validation exception', function () {
    Http::fake(['api.test/api/v1/rota/shifts/3/apply' => Http::response([
        'message' => 'You are not eligible to apply for this shift.',
    ], 422)]);
    bootTenantSession();

    expect(fn () => rotaShifts()->apply(3))
        ->toThrow(ApiValidationException::class);
});

it('lists upcoming shifts and forwards query overrides', function () {
    Http::fake(['api.test/api/v1/rota/shifts/upcoming*' => Http::response([
        'data' => [['type' => 'rota-entries', 'id' => '9', 'attributes' => ['type' => 'Contracted Hours', 'duration' => 8]]],
        'meta' => ['team_id' => 42, 'mode' => 'team', 'days' => 7, 'limit' => 10],
    ])]);
    bootTenantSession();

    $shifts = rotaShifts()->upcoming(['days' => 7, 'mode' => 'team']);

    expect($shifts)->toHaveCount(1)
        ->and($shifts[0])->toBeInstanceOf(Shift::class)
        ->and($shifts[0]->id)->toBe('9');
    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'rota/shifts/upcoming')
            && str_contains($url, 'days=7')
            && str_contains($url, 'mode=team');
    });
});

it('creates a shift and hydrates the stored rota entry', function () {
    Http::fake(['api.test/api/v1/rota/shifts' => Http::response([
        'data' => ['type' => 'rota-entries', 'id' => '51', 'attributes' => [
            'user_id' => 12,
            'type' => 'Overtime Sleeping Night',
            'start' => '2026-08-17T22:00:00+01:00',
            'end' => '2026-08-18T07:00:00+01:00',
            'duration' => 9,
            'is_sleeping_night' => true,
            'is_overtime' => true,
        ]],
        'meta' => ['team_id' => 42],
    ], 201)]);
    bootTenantSession();

    $shift = rotaShifts()->create([
        'assignee_id' => 12,
        'date' => '2026-08-17',
        'start_time' => '22:00',
        'end_time' => '07:00',
        'is_sleeping_night' => true,
    ]);

    expect($shift)->toBeInstanceOf(Shift::class)
        ->and($shift->id)->toBe('51')
        ->and($shift->type)->toBe(EntryType::OvertimeSleepingNight)
        ->and($shift->isSleepingNight)->toBeTrue();
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request['assignee_id'] === 12
        && $request['start_time'] === '22:00'
        && $request->hasHeader('X-Team-ID', '42'));
});

it('maps a 422 on create to a validation exception', function () {
    Http::fake(['api.test/api/v1/rota/shifts' => Http::response([
        'message' => 'The selected assignee is not a member of this team.',
        'errors' => ['assignee_id' => ['The selected assignee is not a member of this team.']],
    ], 422)]);
    bootTenantSession();

    expect(fn () => rotaShifts()->create(['assignee_id' => 999, 'date' => '2026-08-17', 'start_time' => '09:00']))
        ->toThrow(ApiValidationException::class);
});

it('hydrates the wave 2 shift fields and its meta.can flags', function () {
    Http::fake(['api.test/api/v1/rota/shifts/7' => Http::response([
        'data' => [
            'type' => 'rota_entries',
            'id' => '7',
            'attributes' => [
                'user_id' => null,
                'type' => 'Overtime Sleeping Night',
                'duration' => 8,
                'is_available' => true,
                'is_for_toil' => true,
                'is_private' => true,
                'shift_block_id' => 9,
                'tags' => ['Driving', 'Training'],
                'own_application_status' => 'Pending',
            ],
            'meta' => [
                'can' => ['apply' => false, 'request_amendment' => true],
                'cannot_apply_reason' => 'You have already applied for this shift.',
            ],
        ],
    ])]);
    bootTenantSession();

    $shift = rotaShifts()->find(7);

    expect($shift?->isForToil)->toBeTrue()
        ->and($shift->isPrivate)->toBeTrue()
        ->and($shift->shiftBlockId)->toBe(9)
        ->and($shift->tags)->toBe(['Driving', 'Training'])
        ->and($shift->ownApplicationStatus)->toBe('Pending')
        ->and($shift->canApply)->toBeFalse()
        ->and($shift->canRequestAmendment)->toBeTrue()
        ->and($shift->cannotApplyReason)->toBe('You have already applied for this shift.');
});

it('defaults the wave 2 shift fields when the platform omits them', function () {
    Http::fake(['api.test/api/v1/rota/shifts/5' => Http::response([
        'data' => ['type' => 'rota_entries', 'id' => '5', 'attributes' => ['type' => 'Contracted Hours', 'duration' => 8]],
    ])]);
    bootTenantSession();

    $shift = rotaShifts()->find(5);

    expect($shift?->isForToil)->toBeFalse()
        ->and($shift->shiftBlockId)->toBeNull()
        ->and($shift->tags)->toBe([])
        ->and($shift->ownApplicationStatus)->toBeNull()
        ->and($shift->canApply)->toBeFalse()
        ->and($shift->canRequestAmendment)->toBeFalse()
        ->and($shift->cannotApplyReason)->toBeNull();
});

it('requests an amendment to a shift and hydrates the pending request', function () {
    Http::fake(['api.test/api/v1/rota/shifts/51/amendment-requests' => Http::response([
        'data' => ['type' => 'shift_amendment_requests', 'id' => '4', 'attributes' => [
            'rota_entry_id' => 51,
            'proposed_start' => '2026-11-20T15:00:00+00:00',
            'proposed_end' => '2026-11-20T23:00:00+00:00',
            'requested_tags' => ['Driving'],
            'reason' => 'Stayed on to cover the handover',
            'status' => 'Pending',
        ]],
        'meta' => ['team_id' => 42],
    ], 201)]);
    bootTenantSession();

    $amendment = rotaShifts()->requestAmendment(51, [
        'proposed_start_time' => '15:00',
        'proposed_end_time' => '23:00',
        'requested_tags' => ['Driving'],
        'reason' => 'Stayed on to cover the handover',
    ]);

    expect($amendment)->toBeInstanceOf(ShiftAmendmentRequest::class)
        ->and($amendment->id)->toBe('4')
        ->and($amendment->rotaEntryId)->toBe(51)
        ->and($amendment->proposedEnd)->toBe('2026-11-20T23:00:00+00:00')
        ->and($amendment->requestedTags)->toBe(['Driving'])
        ->and($amendment->status)->toBe('Pending');
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request['proposed_start_time'] === '15:00'
        && $request['requested_tags'] === ['Driving']
        && $request->hasHeader('X-Team-ID', '42'));
});

it('maps a 422 on an amendment request to a validation exception', function () {
    Http::fake(['api.test/api/v1/rota/shifts/51/amendment-requests' => Http::response([
        'message' => 'The proposed end time field is required.',
        'errors' => ['proposed_end_time' => ['The proposed end time field is required.']],
    ], 422)]);
    bootTenantSession();

    expect(fn () => rotaShifts()->requestAmendment(51, ['proposed_start_time' => '15:00']))
        ->toThrow(ApiValidationException::class);
});

it('throws when an amendment is not open to the caller (403)', function () {
    Http::fake(['api.test/api/v1/rota/shifts/51/amendment-requests' => Http::response(['message' => 'This action is unauthorized.'], 403)]);
    bootTenantSession();

    expect(fn () => rotaShifts()->requestAmendment(51, ['proposed_start_time' => '15:00', 'proposed_end_time' => '23:00']))
        ->toThrow(SupportManagerApiException::class);
});
