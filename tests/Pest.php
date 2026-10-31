<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Assert;
use SupportManager\Sdk\Data\AuthTokens;
use SupportManager\Sdk\Session\AuthSession;
use SupportManager\Sdk\SupportManager;
use SupportManager\Sdk\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit', 'Contract');

/*
 * Every module's resource tests (tests/Feature/<Module>) run inside a booted
 * tenant session, so no test there has to start one. A new module directory
 * picks this up automatically. tests/Feature/Testing (SupportManager::fake())
 * and the top-level Feature tests, which build or clear sessions on purpose,
 * are left alone.
 */
pest()->beforeEach(fn () => bootTenantSession())->in(...moduleTestDirectories());

/**
 * The module directories under tests/Feature, relative to this file.
 *
 * @return list<string>
 */
function moduleTestDirectories(): array
{
    $directories = glob(__DIR__.'/Feature/*', GLOB_ONLYDIR) ?: [];

    return array_values(array_map(
        static fn (string $directory): string => 'Feature/'.basename($directory),
        array_filter($directories, static fn (string $directory): bool => basename($directory) !== 'Testing'),
    ));
}

/**
 * Boot a real, container-wired session with a cached current team so tenant
 * requests skip the user/teams lookup and carry X-Team-ID straight away.
 *
 * Module tests get this from the beforeEach hook above, and fakeApi() calls it
 * when no session is active; call it directly only for another team.
 *
 * Shared by every test file — do not redefine it (or any helper here) per file:
 * Pest loads all test files together, and a duplicate global function would
 * fatal.
 */
function bootTenantSession(int $teamId = 42): void
{
    $session = app(AuthSession::class);
    $session->start(new AuthTokens('access-1', 'refresh-1', 'Bearer', 3600));
    $session->setCurrentTeamId($teamId);
}

/**
 * The SDK entry point, resolved the way the `SupportManager` facade resolves
 * it, so `sdk()->tasks()` goes through the real accessor and container wiring.
 */
function sdk(): SupportManager
{
    return app(SupportManager::class);
}

/**
 * The faked URL pattern for a path relative to /api/v1, e.g. `tasks/count`
 * or `car-journeys*`.
 */
function apiUrl(string $path): string
{
    return 'api.test/api/v1/'.mb_ltrim($path, '/');
}

/**
 * Fake platform API routes, keyed by path relative to /api/v1:
 *
 * ```php
 * fakeApi([
 *     'tasks/count' => ['data' => ['count' => 5]],                // array: a 200 JSON body
 *     'tasks*' => Http::response(['message' => 'Nope.'], 403),    // or any Http::fake() stub
 *     'https://s3.test/*' => Http::response('%PDF'),              // full URLs pass through
 * ]);
 * ```
 *
 * Starts a tenant session when none is active (module tests already have one),
 * and returns the HTTP factory for further assertions.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeApi(array $routes): Factory
{
    if (app(AuthSession::class)->accessToken() === null) {
        bootTenantSession();
    }

    $stubs = [];

    foreach ($routes as $path => $response) {
        $stubs[str_contains($path, '://') ? $path : apiUrl($path)] = is_array($response) ? Http::response($response) : $response;
    }

    return Http::fake($stubs);
}

/**
 * Assert a request was sent with this method to this path (relative to
 * /api/v1, ignoring the query string), optionally also matching `$also`.
 *
 * @param  (Closure(Request): bool)|null  $also
 */
function assertSentTo(string $method, string $path, ?Closure $also = null): void
{
    $method = mb_strtoupper($method);
    $path = '/api/v1/'.mb_ltrim($path, '/');

    $sent = Http::recorded()->map(fn (array $pair): string => $pair[0]->method().' '.parse_url($pair[0]->url(), PHP_URL_PATH));

    $matched = Http::recorded(fn (Request $request): bool => $request->method() === $method
        && parse_url($request->url(), PHP_URL_PATH) === $path
        && ($also === null || $also($request)));

    Assert::assertTrue($matched->isNotEmpty(), "No {$method} {$path} request".($also === null ? '' : ' matching the callback')." was sent. Sent:\n  "
        .($sent->isEmpty() ? '(nothing)' : $sent->implode("\n  ")));
}
