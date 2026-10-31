<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Rota\Anniversary;
use SupportManager\Sdk\Data\Rota\BankHoliday;
use SupportManager\Sdk\Data\Rota\Birthday;
use SupportManager\Sdk\Data\Rota\CalendarFeed;

it('reads flat bank holiday rows and forwards the date range filter', function () {
    fakeApi(['rota/bank-holidays*' => [
        'data' => [
            ['date' => '2026-12-25', 'name' => 'Christmas Day', 'region' => 'england-and-wales'],
            ['date' => '2026-12-26', 'name' => 'Boxing Day', 'region' => 'england-and-wales'],
        ],
        'meta' => ['team_id' => 42, 'region' => 'england-and-wales'],
    ]]);

    $holidays = sdk()->rotaCalendar()->bankHolidays('2026-01-01', '2026-12-31');

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
    fakeApi(['rota/birthdays*' => [
        'data' => [['date' => '2026-06-10', 'user_id' => 7, 'user_name' => 'Ada']],
    ]]);

    $birthdays = sdk()->rotaCalendar()->birthdays('2026-06-01', '2026-06-30');

    expect($birthdays)->toHaveCount(1)
        ->and($birthdays[0])->toBeInstanceOf(Birthday::class)
        ->and($birthdays[0]->userId)->toBe(7)
        ->and($birthdays[0]->userName)->toBe('Ada');
    assertSentTo('GET', 'rota/birthdays', fn ($request) => str_contains(urldecode($request->url()), 'filter[start_date]=2026-06-01&filter[end_date]=2026-06-30'));
});

it('formats DateTimeInterface bounds as Y-m-d for every date feed', function (string $method, string $path) {
    fakeApi([$path.'*' => ['data' => []]]);

    $result = sdk()->rotaCalendar()->{$method}(new DateTimeImmutable('2026-06-01 09:30:00'), new DateTimeImmutable('2026-06-30 23:59:59'));

    expect($result)->toBe([]);
    assertSentTo('GET', $path, fn ($request) => str_contains(urldecode($request->url()), 'filter[start_date]=2026-06-01&filter[end_date]=2026-06-30'));
})->with([
    'bank holidays' => ['bankHolidays', 'rota/bank-holidays'],
    'birthdays' => ['birthdays', 'rota/birthdays'],
    'anniversaries' => ['anniversaries', 'rota/anniversaries'],
]);

it('requires both ends of the range, which the platform 422s without', function (string $method) {
    $parameters = (new ReflectionMethod(sdk()->rotaCalendar(), $method))->getParameters();

    expect(array_map(fn (ReflectionParameter $p): string => $p->getName(), $parameters))->toBe(['from', 'to'])
        ->and($parameters[0]->isOptional())->toBeFalse()
        ->and($parameters[1]->isOptional())->toBeFalse();
})->with(['bankHolidays', 'birthdays', 'anniversaries']);

it('reads flat anniversary rows including years of service', function () {
    fakeApi(['rota/anniversaries*' => [
        'data' => [['date' => '2026-06-12', 'user_id' => 8, 'user_name' => 'Grace', 'years' => 5]],
    ]]);

    $anniversaries = sdk()->rotaCalendar()->anniversaries('2026-06-01', '2026-06-30');

    expect($anniversaries)->toHaveCount(1)
        ->and($anniversaries[0])->toBeInstanceOf(Anniversary::class)
        ->and($anniversaries[0]->years)->toBe(5);
});

it('reads the calendar feed address', function () {
    fakeApi(['rota/calendar-feed' => [
        'data' => ['url' => 'https://suptmgr.test/calendar/0b6f4f9e.ics'],
        'meta' => ['team_id' => 42],
    ]]);

    $feed = sdk()->rotaCalendar()->feed();

    expect($feed)->toBeInstanceOf(CalendarFeed::class)
        ->and($feed->url)->toBe('https://suptmgr.test/calendar/0b6f4f9e.ics');
    Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->hasHeader('X-Team-ID', '42'));
});

it('reads a null feed address until one is issued', function () {
    fakeApi(['rota/calendar-feed' => ['data' => ['url' => null], 'meta' => ['team_id' => 42]]]);

    expect(sdk()->rotaCalendar()->feed()->url)->toBeNull();
});

it('issues a new calendar feed address', function () {
    fakeApi(['rota/calendar-feed/token' => [
        'data' => ['url' => 'https://suptmgr.test/calendar/new-token.ics'],
        'meta' => ['team_id' => 42],
    ]]);

    $feed = sdk()->rotaCalendar()->regenerateFeed();

    expect($feed->url)->toBe('https://suptmgr.test/calendar/new-token.ics');
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), 'rota/calendar-feed/token'));
});
