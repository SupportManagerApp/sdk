<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Rota\Appointment;

function rotaAppointmentPage(): array
{
    return [
        'data' => [[
            'type' => 'appointments',
            'id' => '4',
            'attributes' => [
                'title' => 'Team review',
                'location' => 'HQ',
                'is_virtual' => false,
                'is_all_day' => false,
                'start' => '2026-06-07T10:00:00Z',
                'end' => '2026-06-07T11:00:00Z',
            ],
        ]],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 1],
        'links' => ['next' => null],
    ];
}

it('encodes filters and tenant header on the appointments index', function () {
    fakeApi(['rota/appointments*' => Http::response(rotaAppointmentPage())]);

    sdk()->appointments()->query()->filter('is_all_day', true)->sort('start')->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[is_all_day]=1')
            && str_contains($url, 'sort=start')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates appointments with pagination metadata', function () {
    fakeApi(['rota/appointments*' => Http::response(rotaAppointmentPage())]);

    $page = sdk()->appointments()->query()->get();

    expect($page->items()[0])->toBeInstanceOf(Appointment::class)
        ->and($page->items()[0]->title)->toBe('Team review')
        ->and($page->items()[0]->isVirtual)->toBeFalse()
        ->and($page->total())->toBe(1);
});

it('finds a single appointment by id', function () {
    fakeApi(['rota/appointments/4' => [
        'data' => ['type' => 'appointments', 'id' => '4', 'attributes' => ['title' => 'One-to-one']],
    ]]);

    expect(sdk()->appointments()->find(4)->title)->toBe('One-to-one');
});

it('returns null for a missing appointment (404)', function () {
    fakeApi(['rota/appointments/999' => Http::response(['message' => 'Not found'], 404)]);

    expect(sdk()->appointments()->find(999))->toBeNull();
});

it('lists upcoming appointments and forwards query overrides', function () {
    fakeApi(['rota/appointments/upcoming*' => [
        'data' => [['type' => 'appointments', 'id' => '4', 'attributes' => ['title' => 'Soon', 'is_all_day' => true]]],
    ]]);

    $upcoming = sdk()->appointments()->upcoming(['days' => 14]);

    expect($upcoming)->toHaveCount(1)
        ->and($upcoming[0])->toBeInstanceOf(Appointment::class)
        ->and($upcoming[0]->isAllDay)->toBeTrue();
    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'days=14'));
});

it('creates an appointment and hydrates the stored record', function () {
    fakeApi(['rota/appointments' => Http::response([
        'data' => ['type' => 'appointments', 'id' => '12', 'attributes' => [
            'title' => 'Dentist',
            'location' => '12 High Street',
            'notes' => 'Bring a list of current medication.',
            'is_virtual' => false,
            'is_all_day' => false,
            'is_private' => true,
            'created_by' => 7,
            'viewer_ids' => [3, 4],
            'start' => '2026-08-18T15:30:00+01:00',
            'end' => '2026-08-18T16:00:00+01:00',
        ]],
        'meta' => ['team_id' => 42],
    ], 201)]);

    $appointment = sdk()->appointments()->create([
        'title' => 'Dentist',
        'date' => '2026-08-18',
        'start_time' => '15:30',
        'duration_minutes' => 30,
    ]);

    expect($appointment)->toBeInstanceOf(Appointment::class)
        ->and($appointment->id)->toBe('12')
        ->and($appointment->notes)->toBe('Bring a list of current medication.')
        ->and($appointment->isPrivate)->toBeTrue()
        ->and($appointment->createdBy)->toBe(7)
        ->and($appointment->viewerIds)->toBe([3, 4])
        ->and($appointment->end)->toBe('2026-08-18T16:00:00+01:00');
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request['duration_minutes'] === 30
        && $request->hasHeader('X-Team-ID', '42'));
});

it('leaves viewer ids null when the platform did not load them', function () {
    fakeApi(['rota/appointments/4' => [
        'data' => ['type' => 'appointments', 'id' => '4', 'attributes' => ['title' => 'One-to-one']],
    ]]);

    $appointment = sdk()->appointments()->find(4);

    expect($appointment->viewerIds)->toBeNull()
        ->and($appointment->isPrivate)->toBeFalse()
        ->and($appointment->notes)->toBeNull();
});

it('hydrates the appointment meta.can flags', function () {
    fakeApi(['rota/appointments/4' => [
        'data' => [
            'type' => 'appointments',
            'id' => '4',
            'attributes' => ['title' => 'One-to-one', 'is_private' => true, 'viewer_ids' => []],
            'meta' => ['can' => ['update' => true, 'delete' => false]],
        ],
    ]]);

    $appointment = sdk()->appointments()->find(4);

    expect($appointment?->canUpdate)->toBeTrue()
        ->and($appointment->canDelete)->toBeFalse()
        ->and($appointment->viewerIds)->toBe([]);
});

it('defaults the appointment permissions to false without meta', function () {
    fakeApi(['rota/appointments/4' => [
        'data' => ['type' => 'appointments', 'id' => '4', 'attributes' => ['title' => 'One-to-one']],
    ]]);

    $appointment = sdk()->appointments()->find(4);

    expect($appointment?->canUpdate)->toBeFalse()
        ->and($appointment->canDelete)->toBeFalse();
});

it('updates an appointment and hydrates the stored record', function () {
    fakeApi(['rota/appointments/4' => [
        'data' => [
            'type' => 'appointments',
            'id' => '4',
            'attributes' => [
                'title' => 'Dentist',
                'start' => '2026-08-25T10:00:00+01:00',
                'end' => '2026-08-25T10:30:00+01:00',
            ],
            'meta' => ['can' => ['update' => true, 'delete' => true]],
        ],
        'meta' => ['team_id' => 42],
    ]]);

    $appointment = sdk()->appointments()->update(4, ['date' => '2026-08-25', 'start_time' => '10:00', 'end_time' => '10:30']);

    expect($appointment)->toBeInstanceOf(Appointment::class)
        ->and($appointment->start)->toBe('2026-08-25T10:00:00+01:00')
        ->and($appointment->end)->toBe('2026-08-25T10:30:00+01:00')
        ->and($appointment->canUpdate)->toBeTrue();
    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), 'rota/appointments/4')
        && $request['start_time'] === '10:00'
        && $request->hasHeader('X-Team-ID', '42'));
});

it('deletes an appointment', function () {
    fakeApi(['rota/appointments/4' => [
        'message' => 'Appointment deleted.',
        'meta' => ['team_id' => 42],
    ]]);

    sdk()->appointments()->destroy(4);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), 'rota/appointments/4')
        && $request->hasHeader('X-Team-ID', '42'));
});
