<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use SupportManager\Sdk\Testing\Json;

/**
 * @return array{int, array<string, mixed>}
 */
function jsonResponseParts(ResponseInterface $response): array
{
    return [$response->getStatusCode(), json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)];
}

it('wraps data in a flat envelope, with meta only when given', function () {
    expect(Json::flat(['url' => null]))->toBe(['data' => ['url' => null]])
        ->and(Json::flat([['date' => '2026-12-25']], ['team_id' => 42]))->toBe(['data' => [['date' => '2026-12-25']], 'meta' => ['team_id' => 42]])
        ->and(Json::flat(null))->toBe(['data' => null]);
});

it('answers a plain message, with an optional status and meta', function () {
    expect(jsonResponseParts(Json::message('Deleted')->wait()))->toBe([200, ['message' => 'Deleted']])
        ->and(jsonResponseParts(Json::message('Not open.', 422, ['team_id' => 7])->wait()))->toBe([422, ['message' => 'Not open.', 'meta' => ['team_id' => 7]]]);
});

it('answers the platform 404 and 403 bodies', function () {
    expect(jsonResponseParts(Json::notFound()->wait()))->toBe([404, ['message' => 'Not found']])
        ->and(jsonResponseParts(Json::notFound('Task not found.')->wait()))->toBe([404, ['message' => 'Task not found.']])
        ->and(jsonResponseParts(Json::forbidden()->wait()))->toBe([403, ['message' => 'This action is unauthorized.']])
        ->and(jsonResponseParts(Json::forbidden('Only employees.')->wait()))->toBe([403, ['message' => 'Only employees.']]);
});

it('answers a validation failure, normalising messages to lists', function () {
    expect(jsonResponseParts(Json::validation(['name' => 'Required', 'date' => ['Too early', 'Not a weekday']])->wait()))->toBe([422, [
        'message' => 'Required',
        'errors' => ['name' => ['Required'], 'date' => ['Too early', 'Not a weekday']],
    ]])->and(jsonResponseParts(Json::validation(['name' => 'Required'], Json::INVALID)->wait()))->toBe([422, [
        'message' => 'The information provided was invalid.',
        'errors' => ['name' => ['Required']],
    ]])->and(jsonResponseParts(Json::validation([])->wait()))->toBe([422, [
        'message' => 'The information provided was invalid.',
        'errors' => [],
    ]]);
});
