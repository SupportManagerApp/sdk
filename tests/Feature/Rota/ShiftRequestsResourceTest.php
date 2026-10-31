<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Rota\ShiftRequest;
use SupportManager\Sdk\Enums\Rota\EntryType;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Resources\Rota\ShiftRequestsResource;

function rotaShiftRequests(): ShiftRequestsResource
{
    return app(ShiftRequestsResource::class);
}

function rotaShiftRequestObject(string $id = '12', array $attributes = [], array $can = ['delete' => true]): array
{
    return [
        'id' => $id,
        'type' => 'shift_requests',
        'attributes' => [
            'team_id' => 42,
            'user_id' => 7,
            'start' => '2026-11-20T09:00:00+00:00',
            'end' => '2026-11-20T17:00:00+00:00',
            'type' => 'Overtime Hours',
            'type_label' => 'Overtime Hours',
            'reason' => 'Covering the late while Sam is away',
            'requested_pay_enhancement_id' => null,
            'status' => 'Pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rota_entry_id' => null,
            'created_at' => '2026-06-06T09:00:00.000000Z',
            'updated_at' => '2026-06-06T09:00:00.000000Z',
            ...$attributes,
        ],
        'meta' => ['can' => $can],
    ];
}

it('encodes filter, sort and tenant header on the shift requests index', function () {
    Http::fake(['api.test/api/v1/rota/shift-requests*' => Http::response([
        'data' => [rotaShiftRequestObject()],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1, 'team_id' => 42],
        'links' => ['next' => null],
    ])]);
    bootTenantSession();

    $page = rotaShiftRequests()->query()->filter('status', 'Pending')->sort('start')->get();

    expect($page->items()[0])->toBeInstanceOf(ShiftRequest::class)
        ->and($page->items()[0]->type)->toBe(EntryType::OvertimeHours)
        ->and($page->items()[0]->reason)->toBe('Covering the late while Sam is away')
        ->and($page->items()[0]->canDelete)->toBeTrue()
        ->and($page->total())->toBe(1);
    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[status]=Pending')
            && str_contains($url, 'sort=start')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates an approved request with its created shift and no delete permission', function () {
    Http::fake(['api.test/api/v1/rota/shift-requests*' => Http::response([
        'data' => [rotaShiftRequestObject('3', [
            'type' => 'Overtime Sleeping Night',
            'status' => 'Approved',
            'reviewed_by' => 2,
            'reviewed_at' => '2026-06-07T09:00:00.000000Z',
            'rota_entry_id' => 51,
            'requested_pay_enhancement_id' => 4,
        ], ['delete' => false])],
    ])]);
    bootTenantSession();

    $request = rotaShiftRequests()->all()->items()[0];

    expect($request->type)->toBe(EntryType::OvertimeSleepingNight)
        ->and($request->status)->toBe('Approved')
        ->and($request->reviewedBy)->toBe(2)
        ->and($request->reviewedAt)->toBe('2026-06-07T09:00:00.000000Z')
        ->and($request->rotaEntryId)->toBe(51)
        ->and($request->requestedPayEnhancementId)->toBe(4)
        ->and($request->canDelete)->toBeFalse();
});

it('creates a shift request and hydrates the pending record', function () {
    Http::fake(['api.test/api/v1/rota/shift-requests' => Http::response([
        'data' => rotaShiftRequestObject('13'),
        'meta' => ['team_id' => 42],
    ], 201)]);
    bootTenantSession();

    $request = rotaShiftRequests()->create([
        'date' => '2026-11-20',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'type' => 'Overtime Hours',
        'reason' => 'Covering the late while Sam is away',
    ]);

    expect($request)->toBeInstanceOf(ShiftRequest::class)
        ->and($request->id)->toBe('13')
        ->and($request->status)->toBe('Pending')
        ->and($request->start)->toBe('2026-11-20T09:00:00+00:00');
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request['type'] === 'Overtime Hours'
        && $request['start_time'] === '09:00'
        && $request->hasHeader('X-Team-ID', '42'));
});

it('maps a 422 on create to a validation exception', function () {
    Http::fake(['api.test/api/v1/rota/shift-requests' => Http::response([
        'message' => 'The selected type is invalid.',
        'errors' => ['type' => ['The selected type is invalid.']],
    ], 422)]);
    bootTenantSession();

    expect(fn () => rotaShiftRequests()->create(['date' => '2026-11-20', 'start_time' => '09:00', 'end_time' => '17:00', 'type' => 'Sickness']))
        ->toThrow(ApiValidationException::class);
});

it('withdraws a shift request', function () {
    Http::fake(['api.test/api/v1/rota/shift-requests/12' => Http::response([
        'message' => 'Shift request deleted.',
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    rotaShiftRequests()->destroy(12);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), 'rota/shift-requests/12')
        && $request->hasHeader('X-Team-ID', '42'));
});

it('throws when withdrawing a request that is no longer open to the caller (403)', function () {
    Http::fake(['api.test/api/v1/rota/shift-requests/12' => Http::response(['message' => 'This action is unauthorized.'], 403)]);
    bootTenantSession();

    expect(fn () => rotaShiftRequests()->destroy(12))
        ->toThrow(SupportManagerApiException::class);
});
