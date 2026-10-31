<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SupportManager\Sdk\Data\Tasks\Task;
use SupportManager\Sdk\Data\Teams\Team;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Http\Download;
use SupportManager\Sdk\Http\DownloadLink;
use SupportManager\Sdk\Http\Paginated;
use SupportManager\Sdk\Tests\Support\HelperProbeResource;

function probe(): HelperProbeResource
{
    return app(HelperProbeResource::class);
}

/**
 * @return array<string, mixed>
 */
function probeTaskRow(string $key = 'profile.address'): array
{
    return ['key' => $key, 'label' => 'Add your address', 'description' => '', 'icon' => 'home', 'completed' => false, 'priority' => 3];
}

/**
 * @return array<string, mixed>
 */
function probeTeamObject(string $id = '42', string $name = 'Lovelace Household'): array
{
    return ['type' => 'teams', 'id' => $id, 'attributes' => ['name' => $name, 'role' => 'admin', 'slug' => 'lovelace']];
}

// ── JSON:API bodies ─────────────────────────────────────────────────────────

it('hydrates a JSON:API item through hydrate(), or null when data is empty', function () {
    Http::fake([
        'api.test/api/v1/probe/one' => Http::response(['data' => probeTeamObject()]),
        'api.test/api/v1/probe/none' => Http::response(['data' => null]),
    ]);
    bootTenantSession();

    expect(probe()->itemFrom('probe/one'))->toBeInstanceOf(Team::class)
        ->and(probe()->itemFrom('probe/one')?->name)->toBe('Lovelace Household')
        ->and(probe()->itemFrom('probe/none'))->toBeNull();
});

it('hydrates a JSON:API item into another DTO when given its class', function () {
    Http::fake(['api.test/api/v1/probe/task' => Http::response(['data' => ['type' => 'tasks', 'id' => '1', 'attributes' => probeTaskRow()]])]);
    bootTenantSession();

    $task = probe()->itemAsTask('probe/task');

    expect($task)->toBeInstanceOf(Task::class)
        ->and($task?->key)->toBe('profile.address');
});

it('hydrates a JSON:API collection through hydrate() or another DTO', function () {
    Http::fake([
        'api.test/api/v1/probe/teams' => Http::response(['data' => [probeTeamObject('1', 'A'), probeTeamObject('2', 'B')]]),
        'api.test/api/v1/probe/tasks' => Http::response(['data' => [['type' => 'tasks', 'id' => '1', 'attributes' => probeTaskRow('a')]]]),
    ]);
    bootTenantSession();

    $teams = probe()->collectionFrom('probe/teams');
    $tasks = probe()->collectionAsTasks('probe/tasks');

    expect($teams)->toHaveCount(2)
        ->and($teams[1]->name)->toBe('B')
        ->and($tasks)->toHaveCount(1)
        ->and($tasks[0])->toBeInstanceOf(Task::class)
        ->and($tasks[0]->key)->toBe('a');
});

it('builds a page from a JSON:API collection with its meta, links and page fetcher', function () {
    Http::fake([
        'api.test/api/v1/probe/teams?page=2' => Http::response([
            'data' => [probeTeamObject('2', 'Second')],
            'meta' => ['current_page' => 2, 'last_page' => 2],
            'links' => ['next' => null],
        ]),
        'api.test/api/v1/probe/teams' => Http::response([
            'data' => [probeTeamObject('1', 'First')],
            'meta' => ['current_page' => 1, 'last_page' => 2, 'unread_count' => 5],
            'links' => ['next' => 'https://api.test/api/v1/probe/teams?page=2'],
        ]),
    ]);
    bootTenantSession();

    $page = probe()->paginatedFrom('probe/teams', fn (int $number): Paginated => probe()->paginatedFrom('probe/teams', query: ['page' => $number]));
    $next = $page->next();

    expect($page->items()[0]->name)->toBe('First')
        ->and($page->meta()['unread_count'])->toBe(5)
        ->and($page->links()['next'])->toBe('https://api.test/api/v1/probe/teams?page=2')
        ->and($next?->items()[0]->name)->toBe('Second')
        ->and($next?->next())->toBeNull();
});

it('builds an unwalkable page when no page fetcher is given', function () {
    Http::fake(['api.test/api/v1/probe/teams' => Http::response([
        'data' => [probeTeamObject()],
        'meta' => ['current_page' => 1, 'last_page' => 3],
    ])]);
    bootTenantSession();

    expect(probe()->paginatedFrom('probe/teams')->next())->toBeNull();
});

// ── Plain {data} bodies ─────────────────────────────────────────────────────

it('hydrates a plain data object into a DTO', function () {
    Http::fake(['api.test/api/v1/probe/task' => Http::response(['data' => probeTaskRow(), 'meta' => ['team_id' => 42]])]);
    bootTenantSession();

    $task = probe()->dataFrom('probe/task');

    expect($task)->toBeInstanceOf(Task::class)
        ->and($task?->label)->toBe('Add your address')
        ->and($task?->priority)->toBe(3);
});

it('returns null from data() when data is missing, null or not an object', function (mixed $body) {
    Http::fake(['api.test/api/v1/probe/task' => Http::response($body)]);
    bootTenantSession();

    expect(probe()->dataFrom('probe/task'))->toBeNull();
})->with([
    'no data member' => [['meta' => []]],
    'null data' => [['data' => null]],
    'scalar data' => [['data' => 'nope']],
    'empty body' => [''],
]);

it('hydrates each object row of a plain data list, skipping anything else', function () {
    Http::fake(['api.test/api/v1/probe/tasks' => Http::response(['data' => [probeTaskRow('a'), 'junk', null, probeTaskRow('b')]])]);
    bootTenantSession();

    $tasks = probe()->dataListFrom('probe/tasks');

    expect($tasks)->toHaveCount(2)
        ->and(array_keys($tasks))->toBe([0, 1])
        ->and($tasks[0]->key)->toBe('a')
        ->and($tasks[1]->key)->toBe('b');
});

it('returns an empty list from dataList() when data is missing', function () {
    Http::fake(['api.test/api/v1/probe/tasks' => Http::response(['meta' => []])]);
    bootTenantSession();

    expect(probe()->dataListFrom('probe/tasks'))->toBe([]);
});

it('builds a page from a plain data list with its meta and links', function () {
    Http::fake([
        'api.test/api/v1/probe/tasks?page=2' => Http::response([
            'data' => [probeTaskRow('c')],
            'meta' => ['current_page' => 2, 'last_page' => 2],
        ]),
        'api.test/api/v1/probe/tasks' => Http::response([
            'data' => [probeTaskRow('a'), probeTaskRow('b')],
            'meta' => ['current_page' => 1, 'last_page' => 2, 'team_id' => 42],
            'links' => ['next' => 'https://api.test/api/v1/probe/tasks?page=2'],
        ]),
    ]);
    bootTenantSession();

    $page = probe()->dataPaginatedFrom('probe/tasks', fn (int $number): Paginated => probe()->dataPaginatedFrom('probe/tasks', query: ['page' => $number]));

    expect($page->items())->toHaveCount(2)
        ->and($page->items()[1]->key)->toBe('b')
        ->and($page->meta()['team_id'])->toBe(42)
        ->and($page->links()['next'])->toBe('https://api.test/api/v1/probe/tasks?page=2')
        ->and($page->next()?->items()[0]->key)->toBe('c');
});

it('defaults a plain page\'s meta and links to empty arrays', function () {
    Http::fake(['api.test/api/v1/probe/tasks' => Http::response(['data' => [probeTaskRow()], 'meta' => 'odd'])]);
    bootTenantSession();

    $page = probe()->dataPaginatedFrom('probe/tasks');

    expect($page->items())->toHaveCount(1)
        ->and($page->meta())->toBe([])
        ->and($page->links())->toBe([])
        ->and($page->next())->toBeNull();
});

it('returns the raw data array, or an empty array when data is not one', function () {
    Http::fake([
        'api.test/api/v1/probe/raw' => Http::response(['data' => ['count' => 3, 'my_tasks' => 1]]),
        'api.test/api/v1/probe/empty' => Http::response(['data' => null]),
    ]);
    bootTenantSession();

    expect(probe()->rawFrom('probe/raw'))->toBe(['count' => 3, 'my_tasks' => 1])
        ->and(probe()->rawFrom('probe/empty'))->toBe([]);
});

it('throws a LogicException when the DTO class has no fromArray()', function () {
    Http::fake(['api.test/api/v1/probe/task' => Http::response(['data' => ['a' => 1]])]);
    bootTenantSession();

    probe()->dataAsUnhydratable('probe/task');
})->throws(LogicException::class, 'stdClass has no static fromArray()');

// ── Files ───────────────────────────────────────────────────────────────────

it('wraps a file body in a Download, forwarding the query and team header', function () {
    Http::fake(['api.test/api/v1/probe/file*' => Http::response('%PDF-1.4 bytes', 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => "attachment; filename=\"Ada.pdf\"; filename*=UTF-8''Ad%C3%A0.pdf",
    ])]);
    bootTenantSession();

    $download = probe()->file('probe/file', ['include_gross_pay' => 1]);

    expect($download)->toBeInstanceOf(Download::class)
        ->and($download->body())->toBe('%PDF-1.4 bytes')
        ->and($download->filename())->toBe('Adà.pdf')
        ->and($download->mimeType())->toBe('application/pdf')
        ->and($download->size())->toBe(14);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'probe/file?include_gross_pay=1')
        && $request->hasHeader('X-Team-ID', '42'));
});

it('hydrates a download link payload into a DownloadLink', function () {
    Http::fake(['api.test/api/v1/probe/link' => Http::response(['data' => [
        'download_url' => 'https://s3.test/signed/7.pdf',
        'filename' => 'contract.pdf',
        'expires_at' => '2026-10-31T14:35:00.000000Z',
    ]])]);
    bootTenantSession();

    $link = probe()->link('probe/link');

    expect($link)->toBeInstanceOf(DownloadLink::class)
        ->and($link->url)->toBe('https://s3.test/signed/7.pdf')
        ->and($link->filename)->toBe('contract.pdf')
        ->and($link->expiresAt)->toBe('2026-10-31T14:35:00.000000Z');
});

it('throws when a download link response carries no URL', function (mixed $body) {
    Http::fake(['api.test/api/v1/probe/link' => Http::response($body)]);
    bootTenantSession();

    probe()->link('probe/link');
})->with([
    'no data' => [['message' => 'ok']],
    'no download_url' => [['data' => ['filename' => 'x.pdf']]],
])->throws(SupportManagerApiException::class, 'did not include a download URL');

// ── Error mapping ───────────────────────────────────────────────────────────

it('maps error responses to typed exceptions in every reader', function (Closure $call, int $status, string $exception, string $message) {
    Http::fake(['api.test/api/v1/probe/*' => Http::response(
        $status === 422
            ? ['message' => 'The year field is invalid.', 'errors' => ['year' => ['The year field is invalid.']]]
            : ['message' => 'This action is unauthorized.'],
        $status,
    )]);
    bootTenantSession();

    expect(fn () => $call(probe()))->toThrow($exception, $message);
})->with([
    'item' => [fn (HelperProbeResource $probe) => $probe->itemFrom('probe/x'), 403, SupportManagerApiException::class, 'This action is unauthorized.'],
    'item as DTO' => [fn (HelperProbeResource $probe) => $probe->itemAsTask('probe/x'), 404, SupportManagerApiException::class, 'This action is unauthorized.'],
    'collection' => [fn (HelperProbeResource $probe) => $probe->collectionAsTasks('probe/x'), 500, SupportManagerApiException::class, 'This action is unauthorized.'],
    'paginated' => [fn (HelperProbeResource $probe) => $probe->paginatedFrom('probe/x'), 422, ApiValidationException::class, 'The year field is invalid.'],
    'data' => [fn (HelperProbeResource $probe) => $probe->dataFrom('probe/x'), 422, ApiValidationException::class, 'The year field is invalid.'],
    'dataList' => [fn (HelperProbeResource $probe) => $probe->dataListFrom('probe/x'), 403, SupportManagerApiException::class, 'This action is unauthorized.'],
    'dataPaginated' => [fn (HelperProbeResource $probe) => $probe->dataPaginatedFrom('probe/x'), 404, SupportManagerApiException::class, 'This action is unauthorized.'],
    'raw' => [fn (HelperProbeResource $probe) => $probe->rawFrom('probe/x'), 500, SupportManagerApiException::class, 'This action is unauthorized.'],
    'downloadFile' => [fn (HelperProbeResource $probe) => $probe->file('probe/x'), 403, SupportManagerApiException::class, 'This action is unauthorized.'],
    'downloadLink' => [fn (HelperProbeResource $probe) => $probe->link('probe/x'), 404, SupportManagerApiException::class, 'This action is unauthorized.'],
    'no content' => [fn (HelperProbeResource $probe) => $probe->noContent('probe/x'), 403, SupportManagerApiException::class, 'This action is unauthorized.'],
]);

it('keeps the status of a mapped download error', function () {
    Http::fake(['api.test/api/v1/probe/file' => Http::response(['message' => 'No document uploaded.'], 404)]);
    bootTenantSession();

    try {
        probe()->file('probe/file');
        test()->fail('Expected SupportManagerApiException');
    } catch (SupportManagerApiException $e) {
        expect($e->status)->toBe(404)
            ->and($e->getMessage())->toBe('No document uploaded.');
    }
});

it('labels the error log with the request path when no context is given', function () {
    Log::spy();
    Http::fake(['api.test/api/v1/probe/broken' => Http::response('<html>oops</html>', 500)]);
    bootTenantSession();

    expect(fn () => probe()->dataFrom('probe/broken'))->toThrow(SupportManagerApiException::class, 'HTTP 500');

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'SupportManager API error'
        && $context['context'] === '/api/v1/probe/broken'
        && $context['status'] === 500);
});

it('passes a successful no-content response through quietly', function () {
    Http::fake(['api.test/api/v1/probe/gone' => Http::response('', 204)]);
    bootTenantSession();

    probe()->noContent('probe/gone');

    Http::assertSent(fn ($request) => $request->method() === 'DELETE');
});
