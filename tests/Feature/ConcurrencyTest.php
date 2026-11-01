<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Client\AuthenticatedApi;
use SupportManager\Sdk\Client\Concurrency;
use SupportManager\Sdk\Client\Settled;
use SupportManager\Sdk\Client\SupportManagerClient;
use SupportManager\Sdk\Data\AuthTokens;
use SupportManager\Sdk\Data\CarJourneys\CarJourney;
use SupportManager\Sdk\Data\Dashboard\Capabilities;
use SupportManager\Sdk\Exceptions\SessionExpiredException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Session\AuthSession;
use SupportManager\Sdk\Session\RefreshSession;
use SupportManager\Sdk\SupportManager;
use SupportManager\Sdk\Testing\Factories\CarJourneyFactory;
use SupportManager\Sdk\Tests\Support\ArraySecureStore;

/**
 * A capabilities body the spec accepts.
 *
 * @return array<string, mixed>
 */
function concurrencyCapabilities(string $role = 'PA'): array
{
    return [
        'data' => [
            'role' => $role,
            'is_privileged' => false,
            'is_employee' => true,
            'is_former_pa' => false,
            'subscribed' => true,
            'plan_features' => ['dashboard' => true],
            'modules' => ['household' => true],
            'navigation' => [],
        ],
        'meta' => ['team_id' => 42],
    ];
}

/**
 * One page of a car-journeys index the spec accepts.
 *
 * @return array<string, mixed>
 */
function concurrencyJourneys(int $page, int $lastPage): array
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

/**
 * Each recorded request as `METHOD /path`, in the order it went out.
 *
 * @return list<string>
 */
function sentInOrder(): array
{
    return Http::recorded()
        ->map(fn (array $pair): string => $pair[0]->method().' '.parse_url($pair[0]->url(), PHP_URL_PATH))
        ->values()
        ->all();
}

function refreshedTokens(): array
{
    return ['data' => [
        'access_token' => 'access-2',
        'refresh_token' => 'refresh-2',
        'token_type' => 'Bearer',
        'expires_in' => 3600,
    ]];
}

it('returns each call’s result under its own key', function () {
    fakeApi([
        'capabilities' => concurrencyCapabilities(),
        'car-journeys*' => Http::response(concurrencyJourneys(1, 1)),
    ]);

    $results = sdk()->concurrently(
        capabilities: fn (SupportManager $sm) => $sm->dashboard()->capabilities(),
        journeys: fn (SupportManager $sm) => $sm->journeys()->query()->get()->items(),
    );

    expect(array_keys($results))->toBe(['capabilities', 'journeys'])
        ->and($results['capabilities'])->toBeInstanceOf(Capabilities::class)
        ->and($results['journeys'][0])->toBeInstanceOf(CarJourney::class);
});

it('keeps positional results in argument order', function () {
    fakeApi([
        'capabilities' => concurrencyCapabilities(),
        'car-journeys*' => Http::response(concurrencyJourneys(1, 1)),
    ]);

    [$capabilities, $page] = sdk()->concurrently(
        fn (SupportManager $sm) => $sm->dashboard()->capabilities(),
        fn (SupportManager $sm) => $sm->journeys()->query()->get(),
    );

    expect($capabilities)->toBeInstanceOf(Capabilities::class)
        ->and($page->items())->toHaveCount(1);
});

it('sends every call’s first request before any call’s second', function () {
    fakeApi([
        'car-journeys*' => Http::sequence()->push(concurrencyJourneys(1, 2))->push(concurrencyJourneys(2, 2)),
        'capabilities' => concurrencyCapabilities(),
    ]);

    sdk()->concurrently(
        fn (SupportManager $sm) => $sm->journeys()->query()->perPage(1)->lazy()->all(),
        fn (SupportManager $sm) => $sm->dashboard()->capabilities(),
    );

    // The page walk's second page waits for the round that sent both first
    // requests, so the capabilities request is not stuck behind it.
    expect(sentInOrder())->toBe([
        'GET /api/v1/car-journeys',
        'GET /api/v1/capabilities',
        'GET /api/v1/car-journeys',
    ]);
});

it('renews the session once when several concurrent calls hit a 401', function () {
    fakeApi([
        'capabilities' => Http::sequence()
            ->push(['message' => 'Unauthenticated.'], 401)
            ->push(concurrencyCapabilities()),
        'car-journeys*' => Http::sequence()
            ->push(['message' => 'Unauthenticated.'], 401)
            ->push(concurrencyJourneys(1, 1)),
        'auth/refresh' => refreshedTokens(),
    ]);

    [$capabilities, $journeys] = sdk()->concurrently(
        fn (SupportManager $sm) => $sm->dashboard()->capabilities(),
        fn (SupportManager $sm) => $sm->journeys()->query()->get(),
    );

    expect($capabilities)->toBeInstanceOf(Capabilities::class)
        ->and($journeys->items())->toHaveCount(1)
        ->and(sentInOrder())->toBe([
            'GET /api/v1/capabilities',
            'GET /api/v1/car-journeys',
            'POST /api/v1/auth/refresh',
            'GET /api/v1/capabilities',
            'GET /api/v1/car-journeys',
        ])
        ->and(app(AuthSession::class)->accessToken())->toBe('access-2');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'car-journeys')
        && $request->hasHeader('Authorization', 'Bearer access-2'));
});

it('still refreshes on its own when a call outside a batch hits a 401 after a renewal', function () {
    fakeApi([
        'capabilities' => Http::sequence()
            ->push(['message' => 'Unauthenticated.'], 401)
            ->push(concurrencyCapabilities()),
        'auth/refresh' => refreshedTokens(),
    ]);

    expect(sdk()->dashboard()->capabilities())->toBeInstanceOf(Capabilities::class);

    Http::assertSentCount(3);
});

it('throws the first failure only after every call has finished', function () {
    fakeApi([
        'capabilities' => Http::response(['message' => 'This action is unauthorized.'], 403),
        'car-journeys*' => Http::response(concurrencyJourneys(1, 1)),
    ]);

    expect(fn () => sdk()->concurrently(
        fn (SupportManager $sm) => $sm->dashboard()->capabilities(),
        fn (SupportManager $sm) => $sm->journeys()->query()->get(),
    ))->toThrow(SupportManagerApiException::class);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'car-journeys'));
});

it('settles each call on its own, keeping the others’ results', function () {
    fakeApi([
        'capabilities' => Http::response(['message' => 'This action is unauthorized.'], 403),
        'car-journeys*' => Http::response(concurrencyJourneys(1, 1)),
    ]);

    $settled = sdk()->settle(
        capabilities: fn (SupportManager $sm) => $sm->dashboard()->capabilities(),
        journeys: fn (SupportManager $sm) => $sm->journeys()->query()->get(),
    );

    expect($settled['capabilities'])->toBeInstanceOf(Settled::class)
        ->and($settled['capabilities']->failed())->toBeTrue()
        ->and($settled['capabilities']->exception())->toBeInstanceOf(SupportManagerApiException::class)
        ->and($settled['journeys']->ok())->toBeTrue()
        ->and($settled['journeys']->value()->items())->toHaveCount(1);

    expect(fn () => $settled['capabilities']->value())->toThrow(SupportManagerApiException::class);
});

it('maps an unreachable API to the usual exception inside a slot', function () {
    bootTenantSession();
    sdk()->fake()->unreachable('car-journeys*');

    $settled = sdk()->settle(fn (SupportManager $sm) => $sm->journeys()->all());

    expect($settled[0]->exception())->toBeInstanceOf(SupportManagerApiException::class);
});

it('surfaces an ended session as SessionExpiredException in every slot', function () {
    app(AuthSession::class)->forget();
    Http::fake();

    $settled = sdk()->settle(
        fn (SupportManager $sm) => $sm->dashboard()->capabilities(),
        fn (SupportManager $sm) => $sm->journeys()->query()->get(),
    );

    expect($settled[0]->exception())->toBeInstanceOf(SessionExpiredException::class)
        ->and($settled[1]->exception())->toBeInstanceOf(SessionExpiredException::class);
});

it('runs a nested batch inside a slot', function () {
    fakeApi([
        'capabilities' => concurrencyCapabilities(),
        'car-journeys*' => Http::response(concurrencyJourneys(1, 1)),
    ]);

    [$inner, $capabilities] = sdk()->concurrently(
        fn (SupportManager $sm) => $sm->concurrently(
            fn (SupportManager $sm) => $sm->journeys()->query()->get(),
            fn (SupportManager $sm) => $sm->journeys()->query()->get(),
        ),
        fn (SupportManager $sm) => $sm->dashboard()->capabilities(),
    );

    expect($inner)->toHaveCount(2)
        ->and($capabilities)->toBeInstanceOf(Capabilities::class);
});

it('runs through SupportManager::fake() like any other call', function () {
    bootTenantSession();
    sdk()->fake()->seed(CarJourneyFactory::new()->count(2));

    [$journeys] = sdk()->concurrently(fn (SupportManager $sm) => $sm->journeys()->all());

    expect($journeys->total())->toBe(2);
});

it('is not in a slot outside a batch', function () {
    expect(Concurrency::inSlot())->toBeFalse();
});

it('rejects a slot that suspends its fiber for something else', function () {
    $settled = Concurrency::run([fn () => Fiber::suspend('not a request')]);

    expect($settled[0]->exception())->toBeInstanceOf(LogicException::class);
});

/*
 * Against a real server: PHP's built-in server with several workers, each
 * request taking 400ms. Three calls one after another would take 1.2s.
 */
it('overlaps the requests on the wire', function () {
    if (! function_exists('proc_open')) {
        $this->markTestSkipped('proc_open is unavailable.');
    }

    $probe = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) parse_url('tcp://'.stream_socket_get_name($probe, false), PHP_URL_PORT);
    fclose($probe);

    $server = proc_open(
        [PHP_BINARY, '-S', "127.0.0.1:{$port}", __DIR__.'/../Support/slow-server.php'],
        [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
        $pipes,
        null,
        [...getenv(), 'PHP_CLI_SERVER_WORKERS' => '4'],
    );

    try {
        $ready = false;

        for ($i = 0; $i < 50 && ! $ready; $i++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            $ready = $socket !== false;
            $ready ? fclose($socket) : usleep(100_000);
        }

        if (! $ready) {
            $this->markTestSkipped('The local test server did not start.');
        }

        $session = new AuthSession(new ArraySecureStore);
        $session->start(new AuthTokens('access-1', 'refresh-1', 'Bearer', 3600));
        $client = new SupportManagerClient("http://127.0.0.1:{$port}", 'Test Device');
        $api = new AuthenticatedApi($client, $session, new RefreshSession($client, $session), app());

        $started = microtime(true);

        $settled = Concurrency::run([
            fn () => $api->send(fn ($request) => $request->get('one'))->json('data.path'),
            fn () => $api->send(fn ($request) => $request->get('two'))->json('data.path'),
            fn () => $api->send(fn ($request) => $request->get('three'))->json('data.path'),
        ]);

        $elapsed = microtime(true) - $started;

        expect(array_map(fn (Settled $outcome) => $outcome->value(), $settled))
            ->toBe(['/api/v1/one', '/api/v1/two', '/api/v1/three'])
            ->and($elapsed)->toBeLessThan(0.9);
    } finally {
        // The server forks its workers; stop them as well as the parent.
        $pid = proc_get_status($server)['pid'];
        exec('pkill -KILL -P '.(int) $pid);
        proc_terminate($server, 9);
        proc_close($server);
    }
});

it('walks lazily at the maximum page size unless one is set', function () {
    fakeApi(['car-journeys*' => Http::response(concurrencyJourneys(1, 1))]);

    sdk()->journeys()->query()->lazy()->all();
    sdk()->journeys()->query()->perPage(5)->lazy()->all();
    sdk()->journeys()->query()->get();

    $pageSizes = Http::recorded()->map(fn (array $pair): mixed => $pair[0]->data()['per_page'] ?? null)->all();

    expect($pageSizes)->toBe([100, 5, null]);
});
