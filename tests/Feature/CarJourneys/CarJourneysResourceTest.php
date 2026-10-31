<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\CarJourneys\CarJourney;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;

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
    fakeApi(['car-journeys*' => Http::response(journeyPage(1, 1))]);

    sdk()->journeys()->query()
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
    fakeApi(['car-journeys*' => Http::response(journeyPage(1, 3))]);

    $page = sdk()->journeys()->query()->get();

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
    fakeApi([
        'car-journeys*' => Http::sequence()
            ->push(journeyPage(1, 2))
            ->push(journeyPage(2, 2)),
    ]);

    $page = sdk()->journeys()->query()->perPage(1)->get();
    $next = $page->next();

    expect($next)->not->toBeNull()
        ->and($next->currentPage())->toBe(2)
        ->and($next->items()[0]->driverName)->toBe('Driver 2')
        ->and($next->hasMorePages())->toBeFalse()
        ->and($next->next())->toBeNull();

    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'page=2'));
});

it('finds a single resource by id', function () {
    fakeApi([
        'car-journeys/7' => [
            'data' => ['type' => 'car-journeys', 'id' => '7', 'attributes' => ['driver_name' => 'Ada', 'is_ongoing' => false, 'trip_end' => '2026-06-06T11:00:00Z']],
        ],
    ]);

    $journey = sdk()->journeys()->find(7);

    expect($journey)->toBeInstanceOf(CarJourney::class)
        ->and($journey->id)->toBe('7')
        ->and($journey->isOngoing)->toBeFalse();
});

it('returns null when finding a missing resource (404)', function () {
    fakeApi(['car-journeys/999' => Http::response(['message' => 'Not found'], 404)]);

    expect(sdk()->journeys()->find(999))->toBeNull();
});

it('hydrates the ongoing journey via the item helper', function () {
    fakeApi([
        'car-journeys/ongoing' => [
            'data' => ['type' => 'car-journeys', 'id' => '5', 'attributes' => ['driver_name' => 'Grace', 'is_ongoing' => true]],
        ],
    ]);

    expect(sdk()->journeys()->ongoing()->driverName)->toBe('Grace');
});

it('returns null from a 404 ongoing lookup before mapping it as an error', function () {
    fakeApi(['car-journeys/ongoing' => Http::response(['message' => 'No ongoing journey found.'], 404)]);

    expect(sdk()->journeys()->ongoing())->toBeNull();
});

it('starts a new journey with no request body', function () {
    fakeApi(['car-journeys/start' => Http::response([
        'data' => ['type' => 'car-journeys', 'id' => '8', 'attributes' => ['driver_name' => 'Hopper', 'is_ongoing' => true]],
    ], 201)]);

    $journey = sdk()->journeys()->start();

    expect($journey->driverName)->toBe('Hopper')
        ->and($journey->isOngoing)->toBeTrue();
    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->data() === []);
});

it('throws when starting while another journey is in progress', function () {
    fakeApi(['car-journeys/start' => Http::response([
        'message' => 'A journey is already in progress.',
        'meta' => ['team_id' => 42],
    ], 422)]);

    expect(fn () => sdk()->journeys()->start())
        ->toThrow(ApiValidationException::class, 'A journey is already in progress.');
});

it('finishes an open journey', function () {
    fakeApi(['car-journeys/8/finish' => [
        'data' => ['type' => 'car-journeys', 'id' => '8', 'attributes' => ['driver_name' => 'Hopper', 'is_ongoing' => false, 'trip_end' => '2026-06-06T12:00:00Z']],
    ]]);

    expect(sdk()->journeys()->finish(8)->isOngoing)->toBeFalse();
});

it('lazily walks every page, fetching on demand', function () {
    fakeApi([
        'car-journeys*' => Http::sequence()
            ->push(journeyPage(1, 3))
            ->push(journeyPage(2, 3))
            ->push(journeyPage(3, 3)),
    ]);

    $drivers = sdk()->journeys()->query()->perPage(1)->lazy()
        ->map(fn (CarJourney $journey) => $journey->driverName)
        ->all();

    expect($drivers)->toBe(['Driver 1', 'Driver 2', 'Driver 3']);
    Http::assertSentCount(3);
});

it('returns a Laravel Collection from collect()', function () {
    fakeApi(['car-journeys*' => Http::response(journeyPage(1, 1))]);

    $collection = sdk()->journeys()->query()->collect();

    expect($collection)->toBeInstanceOf(Collection::class)
        ->and($collection->first())->toBeInstanceOf(CarJourney::class);
});

it('creates a journey record directly', function () {
    fakeApi(['car-journeys' => Http::response([
        'data' => ['type' => 'car-journeys', 'id' => '11', 'attributes' => ['driver_name' => 'Ada', 'trip_start' => '2026-06-06T09:00:00Z', 'is_ongoing' => false]],
    ], 201)]);

    $journey = sdk()->journeys()->create([
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
    fakeApi(['car-journeys/11' => [
        'data' => ['type' => 'car-journeys', 'id' => '11', 'attributes' => ['comments' => 'Edited']],
    ]]);

    $journey = sdk()->journeys()->update(11, [
        'driver_name' => 'Ada',
        'trip_start_date' => '2026-06-06',
        'trip_start_time' => '09:00',
        'comments' => 'Edited',
    ]);

    expect($journey->comments)->toBe('Edited');
    Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_contains($r->url(), 'car-journeys/11'));
});

it('deletes a journey record', function () {
    fakeApi(['car-journeys/11' => ['message' => 'deleted']]);

    sdk()->journeys()->destroy(11);

    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), 'car-journeys/11'));
});

it('hydrates the meta.can permission flags', function () {
    fakeApi(['car-journeys/7' => [
        'data' => [
            'type' => 'car-journeys',
            'id' => '7',
            'attributes' => ['driver_name' => 'Ada', 'trip_start' => '2026-06-06T09:00:00+01:00', 'is_ongoing' => true],
            'meta' => ['can' => ['update' => true, 'delete' => false]],
        ],
        'meta' => ['team_id' => 42],
    ]]);

    $journey = sdk()->journeys()->find(7);

    expect($journey->canUpdate)->toBeTrue()
        ->and($journey->canDelete)->toBeFalse();
});

it('maps a 400 for a privileged-only filter to an api exception', function () {
    fakeApi(['car-journeys*' => Http::response([
        'message' => 'Requested filter(s) `ongoing` are not allowed. Allowed filter(s) are `user_id, driver_name`.',
    ], 400)]);

    try {
        sdk()->journeys()->query()->filter('ongoing', true)->get();
        test()->fail('Expected SupportManagerApiException');
    } catch (SupportManagerApiException $e) {
        expect($e->getCode())->toBe(400)
            ->and($e->getMessage())->toContain('not allowed');
    }
});
