<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Rota\ShiftRequest;
use SupportManager\Sdk\Enums\Rota\EntryType;

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
    fakeApi(['rota/shift-requests*' => [
        'data' => [rotaShiftRequestObject()],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1, 'team_id' => 42],
        'links' => ['next' => null],
    ]]);

    $page = sdk()->shiftRequests()->query()->filter('status', 'Pending')->sort('start')->get();

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
    fakeApi(['rota/shift-requests*' => [
        'data' => [rotaShiftRequestObject('3', [
            'type' => 'Overtime Sleeping Night',
            'status' => 'Approved',
            'reviewed_by' => 2,
            'reviewed_at' => '2026-06-07T09:00:00.000000Z',
            'rota_entry_id' => 51,
            'requested_pay_enhancement_id' => 4,
        ], ['delete' => false])],
    ]]);

    $request = sdk()->shiftRequests()->all()->items()[0];

    expect($request->type)->toBe(EntryType::OvertimeSleepingNight)
        ->and($request->status)->toBe('Approved')
        ->and($request->reviewedBy)->toBe(2)
        ->and($request->reviewedAt)->toBe('2026-06-07T09:00:00.000000Z')
        ->and($request->rotaEntryId)->toBe(51)
        ->and($request->requestedPayEnhancementId)->toBe(4)
        ->and($request->canDelete)->toBeFalse();
});

it('creates a shift request and hydrates the pending record', function () {
    fakeApi(['rota/shift-requests' => Http::response([
        'data' => rotaShiftRequestObject('13'),
        'meta' => ['team_id' => 42],
    ], 201)]);

    $request = sdk()->shiftRequests()->create([
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

it('withdraws a shift request', function () {
    fakeApi(['rota/shift-requests/12' => [
        'message' => 'Shift request deleted.',
        'meta' => ['team_id' => 42],
    ]]);

    sdk()->shiftRequests()->destroy(12);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), 'rota/shift-requests/12')
        && $request->hasHeader('X-Team-ID', '42'));
});
