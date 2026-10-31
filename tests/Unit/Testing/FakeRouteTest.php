<?php

declare(strict_types=1);

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Http\Client\Request;
use Psr\Http\Message\ResponseInterface;
use SupportManager\Sdk\Session\AuthSession;
use SupportManager\Sdk\Testing\Fake;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\RecordStore;
use SupportManager\Sdk\Testing\Registration;

// The route builders are exercised by calling their handlers directly, the way
// the fake does, against a made-up `widgets` resource. Going over the faked
// wire would fail the contract check, because the spec has no such endpoint.

/**
 * Answer one request with a route, as the fake would for the given resource.
 *
 * @param  array<string, mixed>  $body
 */
function answerFakeRoute(FakeRoute $route, RecordStore $store, string $method, string $path, array $body = [], ?string $type = 'widgets', ?Fake $fake = null): ResponseInterface
{
    $matches = $route->match($method, $path);

    expect($matches)->not->toBeNull();

    $request = new Request(new PsrRequest($method, "https://api.test/api/v1/{$path}", ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR)));
    $result = ($route->handler)($store, $matches, $request, $fake ?? new Fake(app(AuthSession::class), 'https://api.test'), new Registration('widgets', $type, [$route]));

    expect($result)->toBeInstanceOf(PromiseInterface::class);

    return $result->wait();
}

/**
 * @return array<string, mixed>
 */
function decodedFakeBody(ResponseInterface $response): array
{
    return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
}

function widgetStore(): RecordStore
{
    $store = new RecordStore;
    $store->put('widgets', [
        ['id' => '1', 'name' => 'Sprocket', 'status' => 'Pending', 'file' => 'sprocket.pdf', 'viewed' => false],
        ['id' => '2', 'name' => 'Cog', 'status' => 'Pending', 'file' => null, 'viewed' => false],
    ]);

    return $store;
}

it('applies an action to the stored record and answers it as an item', function () {
    $store = widgetStore();
    $route = FakeRoute::action('patch', 'widgets/{id}/approve', static fn (array $record, Request $request): array => [
        'status' => 'Approved',
        'note' => $request->data()['note'] ?? null,
    ]);

    $response = answerFakeRoute($route, $store, 'PATCH', 'widgets/1/approve', ['note' => 'Looks good']);

    expect($route->method)->toBe('PATCH')
        ->and($response->getStatusCode())->toBe(200)
        ->and(decodedFakeBody($response))->toBe(['data' => [
            'type' => 'widgets',
            'id' => '1',
            'attributes' => ['name' => 'Sprocket', 'status' => 'Approved', 'file' => 'sprocket.pdf', 'viewed' => false, 'note' => 'Looks good'],
        ]])
        ->and($store->find('widgets', '1')['status'])->toBe('Approved');
});

it('answers a 404 for an action on a record that is not stored', function () {
    $response = answerFakeRoute(
        FakeRoute::action('PATCH', 'widgets/{id}/approve', static fn (): array => ['status' => 'Approved']),
        widgetStore(),
        'PATCH',
        'widgets/99/approve',
    );

    expect($response->getStatusCode())->toBe(404)
        ->and(decodedFakeBody($response))->toBe(['message' => 'Not found']);
});

it('lets an action refuse with a response and leaves the record alone', function () {
    $store = widgetStore();
    $route = FakeRoute::action('PATCH', 'widgets/{id}/withdraw', static fn (array $record): array|PromiseInterface => $record['status'] === 'Pending'
        ? Json::forbidden()
        : ['status' => 'Withdrawn']);

    $response = answerFakeRoute($route, $store, 'PATCH', 'widgets/1/withdraw');

    expect($response->getStatusCode())->toBe(403)
        ->and(decodedFakeBody($response))->toBe(['message' => 'This action is unauthorized.'])
        ->and($store->find('widgets', '1')['status'])->toBe('Pending');
});

it('answers a flat item from an action on a flat resource', function () {
    $response = answerFakeRoute(
        FakeRoute::action('POST', 'widgets/{id}/touch', static fn (): array => ['viewed' => true]),
        widgetStore(),
        'POST',
        'widgets/2/touch',
        type: null,
    );

    expect(decodedFakeBody($response))->toBe(['data' => ['id' => '2', 'name' => 'Cog', 'status' => 'Pending', 'file' => null, 'viewed' => true]]);
});

it('streams a stored record as raw bytes, applying any changes', function () {
    $store = widgetStore();
    $route = FakeRoute::download('widgets/{id}/download', '%PDF-1.4 widget-bytes', changes: ['viewed' => true]);

    $response = answerFakeRoute($route, $store, 'GET', 'widgets/1/download');

    expect($response->getStatusCode())->toBe(200)
        ->and((string) $response->getBody())->toBe('%PDF-1.4 widget-bytes')
        ->and($response->getHeaderLine('Content-Type'))->toBe('application/pdf')
        ->and($response->hasHeader('Content-Disposition'))->toBeFalse()
        ->and($store->find('widgets', '1')['viewed'])->toBeTrue()
        ->and($store->find('widgets', '2')['viewed'])->toBeFalse();
});

it('names a downloaded file and 404s a download that is not stored', function () {
    $route = FakeRoute::download('widgets/{id}/download', 'a,b', 'text/csv', 'widgets.csv');

    $named = answerFakeRoute($route, widgetStore(), 'GET', 'widgets/1/download');
    $missing = answerFakeRoute($route, widgetStore(), 'GET', 'widgets/99/download');

    expect($named->getHeaderLine('Content-Type'))->toBe('text/csv')
        ->and($named->getHeaderLine('Content-Disposition'))->toBe('attachment; filename="widgets.csv"')
        ->and($missing->getStatusCode())->toBe(404);
});

it('answers a signed link to the file an attribute names', function () {
    $route = FakeRoute::downloadLink('widgets/{id}/file', 'file');

    $response = answerFakeRoute($route, widgetStore(), 'GET', 'widgets/1/file');

    expect($response->getStatusCode())->toBe(200)
        ->and(decodedFakeBody($response))->toBe(['data' => [
            'download_url' => 'https://s3.test/signed/widgets/1/sprocket.pdf',
            'filename' => 'sprocket.pdf',
            'expires_at' => '2026-01-01T09:05:00Z',
        ]]);
});

it('404s a signed link when the record or its file is missing', function () {
    $route = FakeRoute::downloadLink('widgets/{id}/file', 'file');

    expect(answerFakeRoute($route, widgetStore(), 'GET', 'widgets/2/file')->getStatusCode())->toBe(404)
        ->and(answerFakeRoute($route, widgetStore(), 'GET', 'widgets/99/file')->getStatusCode())->toBe(404);
});

it('builds a signed link from closures, which may refuse', function () {
    $route = FakeRoute::downloadLink(
        'widgets/{id}/signed',
        static fn (array $record): string|PromiseInterface => $record['status'] === 'Pending' ? "{$record['name']}.pdf" : Json::forbidden(),
        static fn (array $record, string $filename): string => "https://cdn.test/{$record['id']}?name=".rawurlencode($filename),
        expiresAt: '2026-10-31T10:05:00.000000Z',
    );
    $store = widgetStore();

    $link = decodedFakeBody(answerFakeRoute($route, $store, 'GET', 'widgets/1/signed'));
    $store->update('widgets', '1', ['status' => 'Approved']);

    expect($link['data'])->toBe([
        'download_url' => 'https://cdn.test/1?name=Sprocket.pdf',
        'filename' => 'Sprocket.pdf',
        'expires_at' => '2026-10-31T10:05:00.000000Z',
    ])->and(answerFakeRoute($route, $store, 'GET', 'widgets/1/signed')->getStatusCode())->toBe(403);
});

it('lists an unpaginated collection in the resource dialect', function () {
    $route = FakeRoute::collection('widgets/pending', static fn (RecordStore $store): array => array_slice($store->all('widgets'), 0, 1));

    $typed = decodedFakeBody(answerFakeRoute($route, widgetStore(), 'GET', 'widgets/pending'));
    $flat = decodedFakeBody(answerFakeRoute($route, widgetStore(), 'GET', 'widgets/pending', type: null));

    expect($typed)->toBe(['data' => [[
        'type' => 'widgets',
        'id' => '1',
        'attributes' => ['name' => 'Sprocket', 'status' => 'Pending', 'file' => 'sprocket.pdf', 'viewed' => false],
    ]]])->and($flat)->toBe(['data' => [
        ['id' => '1', 'name' => 'Sprocket', 'status' => 'Pending', 'file' => 'sprocket.pdf', 'viewed' => false],
    ]]);
});

it('answers a flat envelope, with the current team in meta on request', function () {
    $fake = new Fake(app(AuthSession::class), 'https://api.test');
    $fake->actingAsTeam(7);

    $plain = FakeRoute::flat('widgets/count', static fn (RecordStore $store): array => ['count' => count($store->all('widgets'))]);
    $scoped = FakeRoute::flat('widgets/reveal', static fn (): array => ['code' => '1234'], teamMeta: true, method: 'post');

    expect(decodedFakeBody(answerFakeRoute($plain, widgetStore(), 'GET', 'widgets/count', fake: $fake)))->toBe(['data' => ['count' => 2]])
        ->and($scoped->method)->toBe('POST')
        ->and(decodedFakeBody(answerFakeRoute($scoped, widgetStore(), 'POST', 'widgets/reveal', fake: $fake)))
        ->toBe(['data' => ['code' => '1234'], 'meta' => ['team_id' => 7]]);
});

it('adds the current team to a collection meta on request', function () {
    $fake = new Fake(app(AuthSession::class), 'https://api.test');
    $fake->actingAsTeam(9);

    $route = FakeRoute::collection('widgets/all', static fn (RecordStore $store): array => [], teamMeta: true);

    expect(decodedFakeBody(answerFakeRoute($route, widgetStore(), 'GET', 'widgets/all', fake: $fake)))
        ->toBe(['data' => [], 'meta' => ['team_id' => 9]]);
});

it('still accepts a plain handler that declares only the parameters it uses', function () {
    $route = FakeRoute::get('widgets/{id}/name', static fn (RecordStore $store, array $matches): mixed => Json::message((string) $store->find('widgets', $matches[1])['name']));

    expect(decodedFakeBody(answerFakeRoute($route, widgetStore(), 'GET', 'widgets/2/name')))->toBe(['message' => 'Cog']);
});
