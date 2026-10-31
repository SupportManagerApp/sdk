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
                'start_time' => '09:00',
                'end_time' => '17:00',
                'user' => ['id' => 7, 'name' => 'Ada'],
                'rota_entry' => ['data' => ['id' => '5', 'type' => 'rota_entries', 'attributes' => ['type' => 'Contracted Hours', 'duration' => 8]]],
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

it('reads the privileged-only provisional flag, null otherwise', function () {
    Http::fake(['api.test/api/v1/rota/shift-applications*' => Http::response([
        'data' => [
            ['type' => 'shift_applications', 'id' => '1', 'attributes' => ['status' => 'Pencilled In', 'is_provisional' => true]],
            ['type' => 'shift_applications', 'id' => '2', 'attributes' => ['status' => 'Pending']],
        ],
    ])]);
    bootTenantSession();

    $items = rotaApplications()->all()->items();

    expect($items[0]->isProvisional)->toBeTrue()
        ->and($items[1]->isProvisional)->toBeNull();
});

it('keeps the nested shift meta.can flags', function () {
    Http::fake(['api.test/api/v1/rota/shift-applications*' => Http::response([
        'data' => [[
            'type' => 'shift_applications',
            'id' => '1',
            'attributes' => [
                'status' => 'Pending',
                'rota_entry' => ['data' => [
                    'id' => '5',
                    'type' => 'rota_entries',
                    'attributes' => ['type' => 'Contracted Hours', 'shift_block_id' => 9],
                    'meta' => ['can' => ['apply' => false, 'request_amendment' => true]],
                ]],
            ],
        ]],
    ])]);
    bootTenantSession();

    $shift = rotaApplications()->all()->items()[0]->rotaEntry;

    expect($shift?->id)->toBe('5')
        ->and($shift->shiftBlockId)->toBe(9)
        ->and($shift->canRequestAmendment)->toBeTrue();
});
