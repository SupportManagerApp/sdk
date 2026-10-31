<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use SupportManager\Sdk\Tests\Contract\Support\AllowlistEntry;
use SupportManager\Sdk\Tests\Contract\Support\ContractValidator;
use SupportManager\Sdk\Tests\Contract\Support\OpenApiSpec;

/*
 * The contract check itself: it must catch drift, and say where. These run
 * the validator directly (without recording coverage).
 */

/**
 * @param  array<string, mixed>|string|null  $body
 * @return list<string>
 */
function contract_violations(string $method, string $url, array|string|null $body, int $status = 200, array $headers = ['Authorization' => 'Bearer t']): array
{
    $response = new Response(
        $status,
        is_string($body) ? ['Content-Type' => 'text/html'] : ['Content-Type' => 'application/json'],
        is_string($body) ? $body : (string) json_encode($body),
    );

    return ContractValidator::instance()->check(new Request($method, "https://api.test{$url}", $headers), $response, record: false);
}

it('passes an exchange that matches the spec', function () {
    expect(contract_violations('POST', '/api/v1/auth/logout', ['data' => ['message' => 'Logged out']]))->toBe([]);
});

it('fails a field the spec does not document, naming the operation, status and pointer', function () {
    expect(contract_violations('POST', '/api/v1/auth/logout', ['data' => ['message' => 'Logged out', 'extra' => 1]]))
        ->toHaveCount(1)
        ->and(contract_violations('POST', '/api/v1/auth/logout', ['data' => ['message' => 'Logged out', 'extra' => 1]])[0])
        ->toContain('POST /auth/logout → 200 response: at /data:')
        ->toContain('additional properties (extra)');
});

it('fails a value of the wrong type', function () {
    expect(contract_violations('POST', '/api/v1/auth/logout', ['data' => ['message' => 5]])[0])
        ->toContain('at /data/message:');
});

it('fails a request missing the documented security', function () {
    expect(contract_violations('POST', '/api/v1/auth/logout', ['data' => ['message' => 'x']], headers: [])[0])
        ->toStartWith('POST /auth/logout request:');
});

it('fails a path the spec does not have, and a method it does not document', function () {
    expect(contract_violations('GET', '/api/v1/payroll/me', ['ok' => true]))
        ->toBe(['GET /payroll/me: no operation in the spec has this path.'])
        ->and(contract_violations('GET', '/api/v1/auth/logout', ['ok' => true]))
        ->toBe(['GET /auth/logout: the spec has this path only for POST.']);
});

it('fails an undocumented success status', function () {
    expect(contract_violations('POST', '/api/v1/auth/logout', ['data' => ['message' => 'x']], 201)[0])
        ->toContain('the spec does not document this status');
});

it('fails an undocumented query parameter', function () {
    expect(contract_violations('GET', '/api/v1/rota/calendar-feed?bogus=1', null, 401, ['Authorization' => 'Bearer t', 'X-Team-ID' => '1'])[0])
        ->toContain("query parameter 'bogus' is not documented");
});

it('checks undocumented error statuses against a Laravel error body', function () {
    expect(contract_violations('POST', '/api/v1/auth/logout', ['message' => 'Not found'], 404))->toBe([])
        ->and(contract_violations('POST', '/api/v1/auth/logout', ['error' => 'Not found'], 404))->not->toBe([]);
});

it('does not check gateway pages or URLs outside the API', function () {
    expect(contract_violations('POST', '/api/v1/auth/logout', '<html>Bad Gateway</html>', 502))->toBe([])
        ->and(contract_violations('GET', '/avatars/1.png', 'bytes'))->toBe([]);
});

it('patches only the operation and pointer an allowlist entry names', function () {
    $entry = AllowlistEntry::fromArray('test', 0, [
        'operation' => 'POST /auth/logout',
        'in' => 200,
        'pointer' => '/data/extra',
        'schema' => ['type' => 'integer'],
        'reason' => 'Test entry.',
    ]);

    $patched = $entry->applyTo(OpenApiSpec::instance()->prepare('POST /auth/logout'));

    expect($patched['responses']['200']['content']['application/json']['schema']['properties']['data']['properties'])
        ->toHaveKeys(['message', 'extra'])
        ->and(OpenApiSpec::instance()->prepare('POST /auth/logout')['responses']['200']['content']['application/json']['schema']['properties']['data']['properties'])
        ->not->toHaveKey('extra');
});

it('rejects an allowlist entry whose pointer no longer matches the spec', function () {
    AllowlistEntry::fromArray('test', 0, [
        'operation' => 'POST /auth/logout',
        'in' => 200,
        'pointer' => '/nope/deeper',
        'nullable' => true,
        'reason' => 'Test entry.',
    ])->applyTo(OpenApiSpec::instance()->prepare('POST /auth/logout'));
})->throws(InvalidArgumentException::class, 'no longer matches');
