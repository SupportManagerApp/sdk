<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Rota\Appointment;
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
