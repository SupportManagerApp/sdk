<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Rota\Anniversary;
use SupportManager\Sdk\Data\Rota\BankHoliday;
use SupportManager\Sdk\Data\Rota\Birthday;
use SupportManager\Sdk\Data\Rota\CalendarFeed;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Resources\Rota\CalendarResource;

function rotaCalendar(): CalendarResource
{
    return app(CalendarResource::class);
}

it('reads flat bank holiday rows and forwards the date range filter', function () {
    Http::fake(['api.test/api/v1/rota/bank-holidays*' => Http::response([
        'data' => [
            ['date' => '2026-12-25', 'name' => 'Christmas Day', 'region' => 'england-and-wales'],
            ['date' => '2026-12-26', 'name' => 'Boxing Day', 'region' => 'england-and-wales'],
        ],
        'meta' => ['team_id' => 42, 'region' => 'england-and-wales'],
    ])]);
    bootTenantSession();

    $holidays = rotaCalendar()->bankHolidays([
        'filter' => ['start_date' => '2026-01-01', 'end_date' => '2026-12-31'],
    ]);

    expect($holidays)->toHaveCount(2)
        ->and($holidays[0])->toBeInstanceOf(BankHoliday::class)
        ->and($holidays[0]->name)->toBe('Christmas Day')
        ->and($holidays[0]->region)->toBe('england-and-wales');

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[start_date]=2026-01-01')
            && str_contains($url, 'filter[end_date]=2026-12-31')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('reads flat birthday rows', function () {
    Http::fake(['api.test/api/v1/rota/birthdays*' => Http::response([
        'data' => [['date' => '2026-06-10', 'user_id' => 7, 'user_name' => 'Ada']],
    ])]);
    bootTenantSession();

    $birthdays = rotaCalendar()->birthdays();

    expect($birthdays)->toHaveCount(1)
        ->and($birthdays[0])->toBeInstanceOf(Birthday::class)
        ->and($birthdays[0]->userId)->toBe(7)
        ->and($birthdays[0]->userName)->toBe('Ada');
});

it('reads flat anniversary rows including years of service', function () {
    Http::fake(['api.test/api/v1/rota/anniversaries*' => Http::response([
        'data' => [['date' => '2026-06-12', 'user_id' => 8, 'user_name' => 'Grace', 'years' => 5]],
    ])]);
    bootTenantSession();

    $anniversaries = rotaCalendar()->anniversaries();

    expect($anniversaries)->toHaveCount(1)
        ->and($anniversaries[0])->toBeInstanceOf(Anniversary::class)
        ->and($anniversaries[0]->years)->toBe(5);
});

it('maps a 422 (missing date range) to a validation exception', function () {
    Http::fake(['api.test/api/v1/rota/bank-holidays*' => Http::response([
        'message' => 'The given data was invalid',
        'errors' => ['filter.start_date' => ['The filter.start date field is required.']],
    ], 422)]);
    bootTenantSession();

    expect(fn () => rotaCalendar()->bankHolidays())
        ->toThrow(ApiValidationException::class);
});

it('reads the calendar feed address', function () {
    Http::fake(['api.test/api/v1/rota/calendar-feed' => Http::response([
        'data' => ['url' => 'https://suptmgr.test/calendar/0b6f4f9e.ics'],
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    $feed = rotaCalendar()->feed();

    expect($feed)->toBeInstanceOf(CalendarFeed::class)
        ->and($feed->url)->toBe('https://suptmgr.test/calendar/0b6f4f9e.ics');
    Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->hasHeader('X-Team-ID', '42'));
});

it('reads a null feed address until one is issued', function () {
    Http::fake(['api.test/api/v1/rota/calendar-feed' => Http::response(['data' => ['url' => null], 'meta' => ['team_id' => 42]])]);
    bootTenantSession();

    expect(rotaCalendar()->feed()->url)->toBeNull();
});

it('issues a new calendar feed address', function () {
    Http::fake(['api.test/api/v1/rota/calendar-feed/token' => Http::response([
        'data' => ['url' => 'https://suptmgr.test/calendar/new-token.ics'],
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    $feed = rotaCalendar()->regenerateFeed();

    expect($feed->url)->toBe('https://suptmgr.test/calendar/new-token.ics');
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), 'rota/calendar-feed/token'));
});

it('throws when the token cannot issue a feed address (403)', function () {
    Http::fake(['api.test/api/v1/rota/calendar-feed/token' => Http::response(['message' => 'Invalid ability provided.'], 403)]);
    bootTenantSession();

    expect(fn () => rotaCalendar()->regenerateFeed())
        ->toThrow(SupportManagerApiException::class);
});
