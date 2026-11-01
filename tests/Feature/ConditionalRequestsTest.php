<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Client\ArrayResponseCache;
use SupportManager\Sdk\Contracts\ResponseCache;
use SupportManager\Sdk\Data\AuthenticatedUser;
use SupportManager\Sdk\Data\AuthTokens;
use SupportManager\Sdk\Session\AuthSession;

/**
 * Start a tenant session that knows its user, as a real sign-in does.
 */
function signedInAs(int $userId, int $teamId = 42): void
{
    $session = app(AuthSession::class);
    $session->start(new AuthTokens('access-1', 'refresh-1', 'Bearer', 3600, user: new AuthenticatedUser($userId, 'Ada', 'ada@test.com')));
    $session->setCurrentTeamId($teamId);
}

function conditionalJourneys(): array
{
    return [
        'data' => [[
            'type' => 'car-journeys',
            'id' => '1',
            'attributes' => ['driver_name' => 'Ada', 'trip_start' => '2026-06-06T10:00:00Z', 'trip_end' => null, 'is_ongoing' => true],
        ]],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1],
        'links' => ['next' => null],
    ];
}

it('sends no If-None-Match and keeps nothing while no cache is bound', function () {
    signedInAs(7);
    Http::fake([apiUrl('car-journeys*') => Http::response(conditionalJourneys(), 200, ['ETag' => 'W/"one"'])]);

    sdk()->journeys()->query()->get();
    sdk()->journeys()->query()->get();

    Http::assertNotSent(fn (Request $request) => $request->hasHeader('If-None-Match'));
});

it('replays the remembered body when the platform answers 304', function () {
    app()->singleton(ResponseCache::class, ArrayResponseCache::class);
    signedInAs(7);

    Http::fake([apiUrl('car-journeys*') => Http::sequence()
        ->push(conditionalJourneys(), 200, ['ETag' => 'W/"one"'])
        ->push('', 304, ['ETag' => 'W/"one"']),
    ]);

    $first = sdk()->journeys()->query()->get();
    $second = sdk()->journeys()->query()->get();

    expect($second->items()[0]->driverName)->toBe('Ada')
        ->and($second->total())->toBe($first->total());

    Http::assertSent(fn (Request $request) => $request->header('If-None-Match') === ['W/"one"']);
});

it('keeps separate entries per person and per team', function () {
    app()->singleton(ResponseCache::class, ArrayResponseCache::class);
    Http::fake([apiUrl('car-journeys*') => Http::response(conditionalJourneys(), 200, ['ETag' => 'W/"one"'])]);

    signedInAs(7, teamId: 42);
    sdk()->journeys()->query()->get();

    signedInAs(8, teamId: 42);
    sdk()->journeys()->query()->get();

    signedInAs(7, teamId: 43);
    sdk()->journeys()->query()->get();

    Http::assertNotSent(fn (Request $request) => $request->hasHeader('If-None-Match'));
});

it('never caches a file download', function () {
    app()->singleton(ResponseCache::class, ArrayResponseCache::class);
    signedInAs(7);
    Http::fake([apiUrl('user/employment/confirmation-letter*') => Http::response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf', 'ETag' => 'W/"pdf"'])]);

    sdk()->profile()->downloadEmploymentConfirmationLetter();
    sdk()->profile()->downloadEmploymentConfirmationLetter();

    Http::assertNotSent(fn (Request $request) => $request->hasHeader('If-None-Match'));
});
