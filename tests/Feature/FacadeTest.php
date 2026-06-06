<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Client\SupportManagerClient;
use SupportManager\Sdk\Data\AuthTokens;
use SupportManager\Sdk\Data\CarJourneys\CarJourney;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Resources\CarJourneys\CarJourneysResource;
use SupportManager\Sdk\Session\AuthSession;
use SupportManager\Sdk\Session\CurrentTeam;

it('exposes the auth, session and team services', function () {
    expect(SupportManager::auth())->toBeInstanceOf(SupportManagerClient::class)
        ->and(SupportManager::session())->toBeInstanceOf(AuthSession::class)
        ->and(SupportManager::team())->toBeInstanceOf(CurrentTeam::class);
});

it('resolves a registered resource fluently and runs a tenant-scoped query', function () {
    Http::fake([
        'api.test/api/v1/car-journeys*' => Http::response([
            'data' => [[
                'type' => 'car-journeys',
                'id' => '1',
                'attributes' => ['driver_name' => 'Ada', 'is_ongoing' => true],
            ]],
            'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1, 'team_id' => '42'],
        ]),
    ]);

    $session = app(AuthSession::class);
    $session->start(new AuthTokens('access-1', 'refresh-1', 'Bearer', 3600));
    $session->setCurrentTeamId(42);

    // Register under a custom name to exercise the by-name __call extension path
    // (the first-party journeys() accessor is a typed method that bypasses it).
    SupportManager::register('trips', CarJourneysResource::class);

    $page = SupportManager::trips()->query()->filter('ongoing', true)->get();

    expect($page->items()[0])->toBeInstanceOf(CarJourney::class)
        ->and($page->items()[0]->driverName)->toBe('Ada');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'car-journeys')
        && $request->hasHeader('X-Team-ID', '42'));
});

it('throws a helpful error for an unregistered resource name', function () {
    expect(fn () => SupportManager::widgets())
        ->toThrow(BadMethodCallException::class, 'registered as [widgets]');
});
