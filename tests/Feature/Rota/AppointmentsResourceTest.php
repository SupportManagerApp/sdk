<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Rota\Appointment;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Resources\Rota\AppointmentsResource;

function rotaAppointments(): AppointmentsResource
{
    return app(AppointmentsResource::class);
}

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
    Http::fake(['api.test/api/v1/rota/appointments*' => Http::response(rotaAppointmentPage())]);
    bootTenantSession();

    rotaAppointments()->query()->filter('is_all_day', true)->sort('start')->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[is_all_day]=1')
            && str_contains($url, 'sort=start')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates appointments with pagination metadata', function () {
    Http::fake(['api.test/api/v1/rota/appointments*' => Http::response(rotaAppointmentPage())]);
    bootTenantSession();

    $page = rotaAppointments()->query()->get();

    expect($page->items()[0])->toBeInstanceOf(Appointment::class)
        ->and($page->items()[0]->title)->toBe('Team review')
        ->and($page->items()[0]->isVirtual)->toBeFalse()
        ->and($page->total())->toBe(1);
});

it('finds a single appointment by id', function () {
    Http::fake(['api.test/api/v1/rota/appointments/4' => Http::response([
        'data' => ['type' => 'appointments', 'id' => '4', 'attributes' => ['title' => 'One-to-one']],
    ])]);
    bootTenantSession();

    expect(rotaAppointments()->find(4)->title)->toBe('One-to-one');
});

it('returns null for a missing appointment (404)', function () {
    Http::fake(['api.test/api/v1/rota/appointments/999' => Http::response(['message' => 'Not found'], 404)]);
    bootTenantSession();

    expect(rotaAppointments()->find(999))->toBeNull();
});

it('lists upcoming appointments and forwards query overrides', function () {
    Http::fake(['api.test/api/v1/rota/appointments/upcoming*' => Http::response([
        'data' => [['type' => 'appointments', 'id' => '4', 'attributes' => ['title' => 'Soon', 'is_all_day' => true]]],
    ])]);
    bootTenantSession();

    $upcoming = rotaAppointments()->upcoming(['days' => 14]);

    expect($upcoming)->toHaveCount(1)
        ->and($upcoming[0])->toBeInstanceOf(Appointment::class)
        ->and($upcoming[0]->isAllDay)->toBeTrue();
    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'days=14'));
});

it('throws an api exception on a server error', function () {
    Http::fake(['api.test/api/v1/rota/appointments*' => Http::response('<html>boom</html>', 500)]);
    bootTenantSession();

    expect(fn () => rotaAppointments()->query()->get())
        ->toThrow(SupportManagerApiException::class);
});

it('creates an appointment and hydrates the stored record', function () {
    Http::fake(['api.test/api/v1/rota/appointments' => Http::response([
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
    bootTenantSession();

    $appointment = rotaAppointments()->create([
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
    Http::fake(['api.test/api/v1/rota/appointments/4' => Http::response([
        'data' => ['type' => 'appointments', 'id' => '4', 'attributes' => ['title' => 'One-to-one']],
    ])]);
    bootTenantSession();

    $appointment = rotaAppointments()->find(4);

    expect($appointment->viewerIds)->toBeNull()
        ->and($appointment->isPrivate)->toBeFalse()
        ->and($appointment->notes)->toBeNull();
});

it('maps a 422 on create to a validation exception', function () {
    Http::fake(['api.test/api/v1/rota/appointments' => Http::response([
        'message' => 'A start time is required unless the appointment is all day.',
        'errors' => ['start_time' => ['A start time is required unless the appointment is all day.']],
    ], 422)]);
    bootTenantSession();

    expect(fn () => rotaAppointments()->create(['title' => 'Dentist', 'date' => '2026-08-18']))
        ->toThrow(ApiValidationException::class);
});
