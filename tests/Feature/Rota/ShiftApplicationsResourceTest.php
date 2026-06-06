<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Rota\ShiftApplication;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Resources\Rota\ShiftApplicationsResource;

function rotaApplications(): ShiftApplicationsResource
{
    return app(ShiftApplicationsResource::class);
}

function rotaApplicationPage(): array
{
    return [
        'data' => [[
            'type' => 'shift-applications',
            'id' => '12',
            'attributes' => [
                'status' => 'Pending',
                'start_time' => '2026-06-06T09:00:00Z',
                'end_time' => '2026-06-06T17:00:00Z',
                'user' => ['id' => 7, 'name' => 'Ada'],
                'rota_entry' => ['id' => '5', 'attributes' => ['type' => 'Contracted Hours', 'duration' => 8]],
            ],
        ]],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1],
        'links' => ['next' => null],
    ];
}

it('encodes filter and tenant header on the applications index', function () {
    Http::fake(['api.test/api/v1/rota/shift-applications*' => Http::response(rotaApplicationPage())]);
    bootTenantSession();

    rotaApplications()->query()->filter('status', 'Pending')->sort('-created_at')->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[status]=Pending')
            && str_contains($url, 'sort=-created_at')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates applications and their nested shift', function () {
    Http::fake(['api.test/api/v1/rota/shift-applications*' => Http::response(rotaApplicationPage())]);
    bootTenantSession();

    $page = rotaApplications()->query()->get();

    expect($page->items()[0])->toBeInstanceOf(ShiftApplication::class)
        ->and($page->items()[0]->status)->toBe('Pending')
        ->and($page->items()[0]->userName)->toBe('Ada')
        ->and($page->items()[0]->rotaEntry?->id)->toBe('5')
        ->and($page->total())->toBe(1);
});

it('cancels a pending application', function () {
    Http::fake(['api.test/api/v1/rota/shift-applications/12' => Http::response(['message' => 'Retracted'])]);
    bootTenantSession();

    rotaApplications()->cancel(12);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), 'rota/shift-applications/12'));
});

it('removes via the alias', function () {
    Http::fake(['api.test/api/v1/rota/shift-applications/12' => Http::response(['message' => 'Retracted'])]);
    bootTenantSession();

    rotaApplications()->remove(12);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE');
});

it('throws when cancelling a non-pending application (422)', function () {
    Http::fake(['api.test/api/v1/rota/shift-applications/12' => Http::response([
        'message' => 'Only pending applications can be retracted.',
    ], 422)]);
    bootTenantSession();

    expect(fn () => rotaApplications()->cancel(12))
        ->toThrow(ApiValidationException::class);
});

it('throws an api exception on a server error', function () {
    Http::fake(['api.test/api/v1/rota/shift-applications*' => Http::response('<html>boom</html>', 500)]);
    bootTenantSession();

    expect(fn () => rotaApplications()->query()->get())
        ->toThrow(SupportManagerApiException::class);
});
