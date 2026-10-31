<?php

declare(strict_types=1);

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use SupportManager\Sdk\Data\Tasks\Task;
use SupportManager\Sdk\Data\Teams\Team;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Http\Download;
use SupportManager\Sdk\Http\DownloadLink;
use SupportManager\Sdk\Http\Paginated;
use SupportManager\Sdk\Tests\Support\HelperProbeResource;

/**
 * The base Resource readers, exercised through a probe resource that answers
 * its own requests from canned responses (see {@see HelperProbeResource}).
 * Real request building, query strings and the X-Team-ID header are covered
 * by each module's own resource tests.
 *
 * @param  array<string, Response>  $responses  Keyed by path (plus `?query`).
 */
function probe(array $responses = []): HelperProbeResource
{
    return app(HelperProbeResource::class)->respond($responses);
}

/**
 * @param  array<string, string>  $headers
 */
function canned(string $uri, mixed $body = [], int $status = 200, array $headers = []): Response
{
    return HelperProbeResource::response($uri, $body, $status, $headers);
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
    $probe = probe([
        'probe/one' => canned('probe/one', ['data' => probeTeamObject()]),
        'probe/none' => canned('probe/none', ['data' => null]),
    ]);

    expect($probe->itemFrom('probe/one'))->toBeInstanceOf(Team::class)
        ->and($probe->itemFrom('probe/one')?->name)->toBe('Lovelace Household')
        ->and($probe->itemFrom('probe/none'))->toBeNull();
});

it('hydrates a JSON:API item into another DTO when given its class', function () {
    $task = probe(['probe/task' => canned('probe/task', ['data' => ['type' => 'tasks', 'id' => '1', 'attributes' => probeTaskRow()]])])
        ->itemAsTask('probe/task');

    expect($task)->toBeInstanceOf(Task::class)
        ->and($task?->key)->toBe('profile.address');
});

it('hydrates a JSON:API collection through hydrate() or another DTO', function () {
    $probe = probe([
        'probe/teams' => canned('probe/teams', ['data' => [probeTeamObject('1', 'A'), probeTeamObject('2', 'B')]]),
        'probe/tasks' => canned('probe/tasks', ['data' => [['type' => 'tasks', 'id' => '1', 'attributes' => probeTaskRow('a')]]]),
    ]);

    $teams = $probe->collectionFrom('probe/teams');
    $tasks = $probe->collectionAsTasks('probe/tasks');

    expect($teams)->toHaveCount(2)
        ->and($teams[1]->name)->toBe('B')
        ->and($tasks)->toHaveCount(1)
        ->and($tasks[0])->toBeInstanceOf(Task::class)
        ->and($tasks[0]->key)->toBe('a');
});

it('builds a page from a JSON:API collection with its meta, links and page fetcher', function () {
    $probe = probe([
        'probe/teams' => canned('probe/teams', [
            'data' => [probeTeamObject('1', 'First')],
            'meta' => ['current_page' => 1, 'last_page' => 2, 'unread_count' => 5],
            'links' => ['next' => 'https://api.test/api/v1/probe/teams?page=2'],
        ]),
        'probe/teams?page=2' => canned('probe/teams?page=2', [
            'data' => [probeTeamObject('2', 'Second')],
            'meta' => ['current_page' => 2, 'last_page' => 2],
            'links' => ['next' => null],
        ]),
    ]);

    $page = $probe->paginatedFrom('probe/teams', fn (int $number): Paginated => $probe->paginatedFrom('probe/teams', query: ['page' => $number]));
    $next = $page->next();

    expect($page->items()[0]->name)->toBe('First')
        ->and($page->meta()['unread_count'])->toBe(5)
        ->and($page->links()['next'])->toBe('https://api.test/api/v1/probe/teams?page=2')
        ->and($next?->items()[0]->name)->toBe('Second')
        ->and($next?->next())->toBeNull();
});

it('builds an unwalkable page when no page fetcher is given', function () {
    $page = probe(['probe/teams' => canned('probe/teams', [
        'data' => [probeTeamObject()],
        'meta' => ['current_page' => 1, 'last_page' => 3],
    ])])->paginatedFrom('probe/teams');

    expect($page->next())->toBeNull();
});

// ── Plain {data} bodies ─────────────────────────────────────────────────────

it('hydrates a plain data object into a DTO', function () {
    $task = probe(['probe/task' => canned('probe/task', ['data' => probeTaskRow(), 'meta' => ['team_id' => 42]])])
        ->dataFrom('probe/task');

    expect($task)->toBeInstanceOf(Task::class)
        ->and($task?->label)->toBe('Add your address')
        ->and($task?->priority)->toBe(3);
});

it('returns null from data() when data is missing, null or not an object', function (mixed $body) {
    expect(probe(['probe/task' => canned('probe/task', $body)])->dataFrom('probe/task'))->toBeNull();
})->with([
    'no data member' => [['meta' => []]],
    'null data' => [['data' => null]],
    'scalar data' => [['data' => 'nope']],
    'empty body' => [''],
]);

it('hydrates each object row of a plain data list, skipping anything else', function () {
    $tasks = probe(['probe/tasks' => canned('probe/tasks', ['data' => [probeTaskRow('a'), 'junk', null, probeTaskRow('b')]])])
        ->dataListFrom('probe/tasks');

    expect($tasks)->toHaveCount(2)
        ->and(array_keys($tasks))->toBe([0, 1])
        ->and($tasks[0]->key)->toBe('a')
        ->and($tasks[1]->key)->toBe('b');
});

it('returns an empty list from dataList() when data is missing', function () {
    expect(probe(['probe/tasks' => canned('probe/tasks', ['meta' => []])])->dataListFrom('probe/tasks'))->toBe([]);
});

it('builds a page from a plain data list with its meta and links', function () {
    $probe = probe([
        'probe/tasks' => canned('probe/tasks', [
            'data' => [probeTaskRow('a'), probeTaskRow('b')],
            'meta' => ['current_page' => 1, 'last_page' => 2, 'team_id' => 42],
            'links' => ['next' => 'https://api.test/api/v1/probe/tasks?page=2'],
        ]),
        'probe/tasks?page=2' => canned('probe/tasks?page=2', [
            'data' => [probeTaskRow('c')],
            'meta' => ['current_page' => 2, 'last_page' => 2],
        ]),
    ]);

    $page = $probe->dataPaginatedFrom('probe/tasks', fn (int $number): Paginated => $probe->dataPaginatedFrom('probe/tasks', query: ['page' => $number]));

    expect($page->items())->toHaveCount(2)
        ->and($page->items()[1]->key)->toBe('b')
        ->and($page->meta()['team_id'])->toBe(42)
        ->and($page->links()['next'])->toBe('https://api.test/api/v1/probe/tasks?page=2')
        ->and($page->next()?->items()[0]->key)->toBe('c');
});

it('defaults a plain page\'s meta and links to empty arrays', function () {
    $page = probe(['probe/tasks' => canned('probe/tasks', ['data' => [probeTaskRow()], 'meta' => 'odd'])])
        ->dataPaginatedFrom('probe/tasks');

    expect($page->items())->toHaveCount(1)
        ->and($page->meta())->toBe([])
        ->and($page->links())->toBe([])
        ->and($page->next())->toBeNull();
});

it('returns the raw data array, or an empty array when data is not one', function () {
    $probe = probe([
        'probe/raw' => canned('probe/raw', ['data' => ['count' => 3, 'my_tasks' => 1]]),
        'probe/empty' => canned('probe/empty', ['data' => null]),
    ]);

    expect($probe->rawFrom('probe/raw'))->toBe(['count' => 3, 'my_tasks' => 1])
        ->and($probe->rawFrom('probe/empty'))->toBe([]);
});

it('throws a LogicException when the DTO class has no fromArray()', function () {
    probe(['probe/task' => canned('probe/task', ['data' => ['a' => 1]])])->dataAsUnhydratable('probe/task');
})->throws(LogicException::class, 'stdClass has no static fromArray()');

// ── Files ───────────────────────────────────────────────────────────────────

it('wraps a file body in a Download, forwarding the query', function () {
    $probe = probe(['probe/file?include_gross_pay=1' => canned('probe/file?include_gross_pay=1', '%PDF-1.4 bytes', 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => "attachment; filename=\"Ada.pdf\"; filename*=UTF-8''Ad%C3%A0.pdf",
    ])]);

    $download = $probe->file('probe/file', ['include_gross_pay' => 1]);

    expect($download)->toBeInstanceOf(Download::class)
        ->and($download->body())->toBe('%PDF-1.4 bytes')
        ->and($download->filename())->toBe('Adà.pdf')
        ->and($download->mimeType())->toBe('application/pdf')
        ->and($download->size())->toBe(14)
        ->and($probe->sent)->toBe([['method' => 'GET', 'uri' => 'probe/file?include_gross_pay=1']]);
});

it('hydrates a download link payload into a DownloadLink', function () {
    $link = probe(['probe/link' => canned('probe/link', ['data' => [
        'download_url' => 'https://s3.test/signed/7.pdf',
        'filename' => 'contract.pdf',
        'expires_at' => '2026-10-31T14:35:00.000000Z',
    ]])])->link('probe/link');

    expect($link)->toBeInstanceOf(DownloadLink::class)
        ->and($link->url)->toBe('https://s3.test/signed/7.pdf')
        ->and($link->filename)->toBe('contract.pdf')
        ->and($link->expiresAt)->toBe('2026-10-31T14:35:00.000000Z');
});

it('throws when a download link response carries no URL', function (mixed $body) {
    probe(['probe/link' => canned('probe/link', $body)])->link('probe/link');
})->with([
    'no data' => [['message' => 'ok']],
    'no download_url' => [['data' => ['filename' => 'x.pdf']]],
])->throws(SupportManagerApiException::class, 'did not include a download URL');

// ── Error mapping ───────────────────────────────────────────────────────────

it('maps error responses to typed exceptions in every reader', function (Closure $call, int $status, string $exception, string $message) {
    $probe = probe(['probe/x' => canned('probe/x', $status === 422
        ? ['message' => 'The year field is invalid.', 'errors' => ['year' => ['The year field is invalid.']]]
        : ['message' => 'This action is unauthorized.'], $status)]);

    expect(fn () => $call($probe))->toThrow($exception, $message);
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
    try {
        probe(['probe/file' => canned('probe/file', ['message' => 'No document uploaded.'], 404)])->file('probe/file');
        test()->fail('Expected SupportManagerApiException');
    } catch (SupportManagerApiException $e) {
        expect($e->status)->toBe(404)
            ->and($e->getMessage())->toBe('No document uploaded.');
    }
});

it('labels the error log with the request path when no context is given', function () {
    Log::spy();

    expect(fn () => probe(['probe/broken' => canned('probe/broken', '<html>oops</html>', 500)])->dataFrom('probe/broken'))
        ->toThrow(SupportManagerApiException::class, 'HTTP 500');

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'SupportManager API error'
        && $context['context'] === '/api/v1/probe/broken'
        && $context['status'] === 500);
});

it('passes a successful no-content response through quietly', function () {
    $probe = probe(['probe/gone' => canned('probe/gone', '', 204)]);

    $probe->noContent('probe/gone');

    expect($probe->sent)->toBe([['method' => 'DELETE', 'uri' => 'probe/gone']]);
});
