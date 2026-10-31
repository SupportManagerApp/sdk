<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\CarJourneys\CarJourney;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Resources\CarJourneys\CarJourneysResource;

function journeys(): CarJourneysResource
{
    return app(CarJourneysResource::class);
}

function journeyPage(int $page, int $lastPage): array
{
    return [
        'data' => [[
            'type' => 'car-journeys',
            'id' => (string) $page,
            'attributes' => ['driver_name' => "Driver {$page}", 'trip_start' => '2026-06-06T10:00:00Z', 'trip_end' => null, 'is_ongoing' => true],
        ]],
        'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => 1, 'total' => $lastPage],
        'links' => ['next' => $page < $lastPage ? 'https://api.test/api/v1/car-journeys?page='.($page + 1) : null],
    ];
}

it('encodes filter, sort and pagination as Spatie-style query params', function () {
    Http::fake(['api.test/api/v1/car-journeys*' => Http::response(journeyPage(1, 1))]);
    bootTenantSession();

    journeys()->query()
        ->filter('ongoing', true)
        ->sort('-trip_start', 'driver_name')
        ->perPage(15)
        ->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[ongoing]=1')
            && str_contains($url, 'sort=-trip_start,driver_name')
            && str_contains($url, 'per_page=15')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates DTOs and exposes pagination metadata', function () {
    Http::fake(['api.test/api/v1/car-journeys*' => Http::response(journeyPage(1, 3))]);
    bootTenantSession();

    $page = journeys()->query()->get();

    expect($page->items())->toHaveCount(1)
        ->and($page->items()[0])->toBeInstanceOf(CarJourney::class)
        ->and($page->items()[0]->driverName)->toBe('Driver 1')
        ->and($page->currentPage())->toBe(1)
        ->and($page->lastPage())->toBe(3)
        ->and($page->total())->toBe(3)
        ->and($page->hasMorePages())->toBeTrue()
        ->and(count($page))->toBe(1);
});

it('walks to the next page by re-running the query', function () {
    Http::fake([
        'api.test/api/v1/car-journeys*' => Http::sequence()
            ->push(journeyPage(1, 2))
            ->push(journeyPage(2, 2)),
    ]);
    bootTenantSession();

    $page = journeys()->query()->perPage(1)->get();
    $next = $page->next();

    expect($next)->not->toBeNull()
        ->and($next->currentPage())->toBe(2)
        ->and($next->items()[0]->driverName)->toBe('Driver 2')
        ->and($next->hasMorePages())->toBeFalse()
        ->and($next->next())->toBeNull();

    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'page=2'));
});

it('finds a single resource by id', function () {
    Http::fake([
        'api.test/api/v1/car-journeys/7' => Http::response([
            'data' => ['type' => 'car-journeys', 'id' => '7', 'attributes' => ['driver_name' => 'Ada', 'is_ongoing' => false, 'trip_end' => '2026-06-06T11:00:00Z']],
        ]),
    ]);
    bootTenantSession();

    $journey = journeys()->find(7);

    expect($journey)->toBeInstanceOf(CarJourney::class)
        ->and($journey->id)->toBe('7')
        ->and($journey->isOngoing)->toBeFalse();
});

it('returns null when finding a missing resource (404)', function () {
    Http::fake(['api.test/api/v1/car-journeys/999' => Http::response(['message' => 'Not found'], 404)]);
    bootTenantSession();

    expect(journeys()->find(999))->toBeNull();
});

it('hydrates the ongoing journey via the item helper', function () {
    Http::fake([
        'api.test/api/v1/car-journeys/ongoing' => Http::response([
            'data' => ['type' => 'car-journeys', 'id' => '5', 'attributes' => ['driver_name' => 'Grace', 'is_ongoing' => true]],
        ]),
    ]);
    bootTenantSession();

    expect(journeys()->ongoing()->driverName)->toBe('Grace');
});

it('returns null from a 404 ongoing lookup before mapping it as an error', function () {
    Http::fake(['api.test/api/v1/car-journeys/ongoing' => Http::response(['message' => 'No ongoing journey found.'], 404)]);
    bootTenantSession();

    expect(journeys()->ongoing())->toBeNull();
});

it('starts a new journey with no request body', function () {
    Http::fake(['api.test/api/v1/car-journeys/start' => Http::response([
        'data' => ['type' => 'car-journeys', 'id' => '8', 'attributes' => ['driver_name' => 'Hopper', 'is_ongoing' => true]],
    ], 201)]);
    bootTenantSession();

    $journey = journeys()->start();

    expect($journey->driverName)->toBe('Hopper')
        ->and($journey->isOngoing)->toBeTrue();
    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->data() === []);
});

it('throws when starting while another journey is in progress', function () {
    Http::fake(['api.test/api/v1/car-journeys/start' => Http::response([
        'message' => 'A journey is already in progress.',
        'meta' => ['team_id' => 42],
    ], 422)]);
    bootTenantSession();

    expect(fn () => journeys()->start())
        ->toThrow(ApiValidationException::class, 'A journey is already in progress.');
});

it('finishes an open journey', function () {
    Http::fake(['api.test/api/v1/car-journeys/8/finish' => Http::response([
        'data' => ['type' => 'car-journeys', 'id' => '8', 'attributes' => ['driver_name' => 'Hopper', 'is_ongoing' => false, 'trip_end' => '2026-06-06T12:00:00Z']],
    ])]);
    bootTenantSession();

    expect(journeys()->finish(8)->isOngoing)->toBeFalse();
});

it('maps a 422 (invalid team context) on the resource path to a validation exception', function () {
    Http::fake(['api.test/api/v1/car-journeys*' => Http::response([
        'message' => 'Invalid or missing team. Please specify a valid team ID via X-Team-ID header or team_id parameter.',
    ], 422)]);
    bootTenantSession();

    try {
        journeys()->query()->get();
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->getMessage())->toBe('Invalid or missing team. Please specify a valid team ID via X-Team-ID header or team_id parameter.')
            ->and($e->errors)->toBe([]);
    }
});

it('maps a 500 on the resource path to an api exception instead of an empty page', function () {
    Http::fake(['api.test/api/v1/car-journeys*' => Http::response('<html>boom</html>', 500)]);
    bootTenantSession();

    expect(fn () => journeys()->query()->get())
        ->toThrow(SupportManagerApiException::class);
});

it('lazily walks every page, fetching on demand', function () {
    Http::fake([
        'api.test/api/v1/car-journeys*' => Http::sequence()
            ->push(journeyPage(1, 3))
            ->push(journeyPage(2, 3))
            ->push(journeyPage(3, 3)),
    ]);
    bootTenantSession();

    $drivers = journeys()->query()->perPage(1)->lazy()
        ->map(fn (CarJourney $journey) => $journey->driverName)
        ->all();

    expect($drivers)->toBe(['Driver 1', 'Driver 2', 'Driver 3']);
    Http::assertSentCount(3);
});

it('returns a Laravel Collection from collect()', function () {
    Http::fake(['api.test/api/v1/car-journeys*' => Http::response(journeyPage(1, 1))]);
    bootTenantSession();

    $collection = journeys()->query()->collect();

    expect($collection)->toBeInstanceOf(Collection::class)
        ->and($collection->first())->toBeInstanceOf(CarJourney::class);
});

it('creates a journey record directly', function () {
    Http::fake(['api.test/api/v1/car-journeys' => Http::response([
        'data' => ['type' => 'car-journeys', 'id' => '11', 'attributes' => ['driver_name' => 'Ada', 'trip_start' => '2026-06-06T09:00:00Z', 'is_ongoing' => false]],
    ], 201)]);
    bootTenantSession();

    $journey = journeys()->create([
        'driver_name' => 'Ada',
        'trip_start_date' => '2026-06-06',
        'trip_start_time' => '09:00',
        'trip_end_date' => '2026-06-06',
        'trip_end_time' => '10:30',
    ]);

    expect($journey)->toBeInstanceOf(CarJourney::class)
        ->and($journey->id)->toBe('11')
        ->and($journey->driverName)->toBe('Ada');
    Http::assertSent(fn ($r) => $r->method() === 'POST'
        && str_ends_with($r->url(), '/api/v1/car-journeys')
        && $r['driver_name'] === 'Ada');
});

it('updates a journey record', function () {
    Http::fake(['api.test/api/v1/car-journeys/11' => Http::response([
        'data' => ['type' => 'car-journeys', 'id' => '11', 'attributes' => ['comments' => 'Edited']],
    ])]);
    bootTenantSession();

    $journey = journeys()->update(11, [
        'driver_name' => 'Ada',
        'trip_start_date' => '2026-06-06',
        'trip_start_time' => '09:00',
        'comments' => 'Edited',
    ]);

    expect($journey->comments)->toBe('Edited');
    Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_contains($r->url(), 'car-journeys/11'));
});

it('deletes a journey record', function () {
    Http::fake(['api.test/api/v1/car-journeys/11' => Http::response(['message' => 'deleted'])]);
    bootTenantSession();

    journeys()->destroy(11);

    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), 'car-journeys/11'));
});

it('hydrates the meta.can permission flags', function () {
    Http::fake(['api.test/api/v1/car-journeys/7' => Http::response([
        'data' => [
            'type' => 'car-journeys',
            'id' => '7',
            'attributes' => ['driver_name' => 'Ada', 'trip_start' => '2026-06-06T09:00:00+01:00', 'is_ongoing' => true],
            'meta' => ['can' => ['update' => true, 'delete' => false]],
        ],
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    $journey = journeys()->find(7);

    expect($journey->canUpdate)->toBeTrue()
        ->and($journey->canDelete)->toBeFalse();
});

it('maps a 400 for a privileged-only filter to an api exception', function () {
    Http::fake(['api.test/api/v1/car-journeys*' => Http::response([
        'message' => 'Requested filter(s) `ongoing` are not allowed. Allowed filter(s) are `user_id, driver_name`.',
    ], 400)]);
    bootTenantSession();

    try {
        journeys()->query()->filter('ongoing', true)->get();
        test()->fail('Expected SupportManagerApiException');
    } catch (SupportManagerApiException $e) {
        expect($e->getCode())->toBe(400)
            ->and($e->getMessage())->toContain('not allowed');
    }
});
