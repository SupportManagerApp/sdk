<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\AuthTokens;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Session\AuthSession;
use SupportManager\Sdk\Tests\Fixtures\CarJourneys\CarJourney;
use SupportManager\Sdk\Tests\Fixtures\CarJourneys\CarJourneysResource;

/**
 * Boot a real, container-wired session with a cached current team so tenant
 * requests skip the user/teams lookup and carry X-Team-ID straight away.
 */
function bootTenantSession(int $teamId = 42): void
{
    $session = app(AuthSession::class);
    $session->start(new AuthTokens('access-1', 'refresh-1', 'Bearer', 3600));
    $session->setCurrentTeamId($teamId);
}

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

it('encodes filter, sort, include and pagination as Spatie-style query params', function () {
    Http::fake(['api.test/api/v1/car-journeys*' => Http::response(journeyPage(1, 1))]);
    bootTenantSession();

    journeys()->query()
        ->filter('ongoing', true)
        ->sort('-trip_start', 'driver_name')
        ->include('user')
        ->perPage(15)
        ->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[ongoing]=1')
            && str_contains($url, 'sort=-trip_start,driver_name')
            && str_contains($url, 'include=user')
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

it('hydrates a single DTO from a non-RESTful action via the item helper', function () {
    Http::fake([
        'api.test/api/v1/car-journeys/ongoing' => Http::response([
            'data' => ['type' => 'car-journeys', 'id' => '5', 'attributes' => ['driver_name' => 'Grace', 'is_ongoing' => true]],
        ]),
    ]);
    bootTenantSession();

    expect(journeys()->ongoing()->driverName)->toBe('Grace');
});

it('returns null from a 404 action before mapping it as an error', function () {
    Http::fake(['api.test/api/v1/car-journeys/ongoing' => Http::response(['message' => 'No ongoing journey found.'], 404)]);
    bootTenantSession();

    expect(journeys()->ongoing())->toBeNull();
});

it('maps a 422 on the resource path to a validation exception', function () {
    Http::fake(['api.test/api/v1/car-journeys*' => Http::response([
        'message' => 'The given data was invalid',
        'errors' => ['filter' => ['The ongoing filter is invalid.']],
    ], 422)]);
    bootTenantSession();

    try {
        journeys()->query()->filter('ongoing', 'maybe')->get();
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('filter'))->toBe('The ongoing filter is invalid.');
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
