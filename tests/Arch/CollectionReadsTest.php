<?php

declare(strict_types=1);

use SupportManager\Sdk\Http\ResourceQuery;
use SupportManager\Sdk\Resources\Concerns\Findable;
use SupportManager\Sdk\Resources\Concerns\Listable;
use SupportManager\Sdk\Resources\Resource;
use SupportManager\Sdk\Tests\Arch\Support\Codebase;
use SupportManager\Sdk\Tests\Contract\Support\OpenApiSpec;

/*
 * A resource offers find(), all() and query() only where the platform has the
 * route behind them, judged against the vendored spec
 * (tests/Contract/openapi.yaml):
 *
 * - find($id) needs `GET {path}/{param}`.
 * - all() and query() need `GET {path}`.
 *
 * The generic readers from the Findable and Listable traits also read a
 * JSON:API document, so for them the spec's 200 must be one: a resource
 * object for find(), a list of them for all() and query(). A resource whose
 * index is a singleton or a plain `{data}` list writes its own method (or has
 * none) instead. Every resource is checked, so a new one is too.
 */

/**
 * The resource's collection path, e.g. `household/chores`.
 *
 * @param  class-string<Resource<mixed>>  $resource
 */
function collectionPath(string $resource): string
{
    $instance = (new ReflectionClass($resource))->newInstanceWithoutConstructor();

    return (string) (new ReflectionMethod($resource, 'path'))->invoke($instance);
}

/**
 * The spec's `GET {path}/{param}` operation, or null.
 */
function showOperation(string $path): ?string
{
    foreach (OpenApiSpec::instance()->operationKeys() as $operation) {
        if (preg_match('#^GET /'.preg_quote($path, '#').'/\{[^/}]+\}$#', $operation) === 1) {
            return $operation;
        }
    }

    return null;
}

/**
 * The schema of the `data` member of the operation's 200 JSON body.
 *
 * @return array<array-key, mixed>
 */
function successData(string $operation): array
{
    /** @var array<array-key, mixed> $schema */
    $schema = OpenApiSpec::instance()->prepare($operation)['responses']['200']['content']['application/json']['schema'] ?? [];

    return $schema['properties']['data'] ?? [];
}

/**
 * @param  array<array-key, mixed>  $schema
 */
function isResourceObject(array $schema): bool
{
    return ($schema['type'] ?? null) === 'object' && isset($schema['properties']['attributes']);
}

/**
 * Whether the class has the public method, and the trait that provides it if
 * it is not the class's own.
 *
 * @param  class-string  $resource
 * @return array{bool, string|null}
 */
function publicMethodSource(string $resource, string $method): array
{
    if (! method_exists($resource, $method) || ! (new ReflectionMethod($resource, $method))->isPublic()) {
        return [false, null];
    }

    $file = (new ReflectionMethod($resource, $method))->getFileName();

    foreach ([Findable::class, Listable::class] as $trait) {
        if ($file === (new ReflectionClass($trait))->getFileName()) {
            return [true, $trait];
        }
    }

    return [true, null];
}

it('offers find() only where the platform has GET {path}/{id}', function (string $resource) {
    [$has, $trait] = publicMethodSource($resource, 'find');

    if (! $has) {
        expect(true)->toBeTrue();

        return;
    }

    $path = collectionPath($resource);
    $operation = showOperation($path);

    expect($operation)->not->toBeNull("{$resource} has find(), but the spec has no GET /{$path}/{id}. Drop the Findable trait (or the method).");

    if ($trait === Findable::class) {
        expect(isResourceObject(successData((string) $operation)))
            ->toBeTrue("{$resource} uses Findable, but {$operation} does not answer with a JSON:API resource object. Write a find() that reads what it does send.");
    }
})->with(fn () => Codebase::resources());

it('offers all() and query() only where the platform has GET {path}', function (string $resource) {
    $path = collectionPath($resource);
    $operation = "GET /{$path}";

    foreach (['all', 'query'] as $method) {
        [$has, $trait] = publicMethodSource($resource, $method);

        if (! $has) {
            continue;
        }

        expect(OpenApiSpec::instance()->has($operation))
            ->toBeTrue("{$resource} has {$method}(), but the spec has no {$operation}. Drop the Listable trait (or the method).");

        if ($trait === Listable::class) {
            $data = successData($operation);

            expect(($data['type'] ?? null) === 'array' && isResourceObject($data['items'] ?? []))
                ->toBeTrue("{$resource} uses Listable, but {$operation} does not answer with a JSON:API collection. Write an all() that reads what it does send.");
        }
    }

    expect(true)->toBeTrue();
})->with(fn () => Codebase::resources());

it('catches a find() the platform has no route for', function () {
    // The two that shipped in v0.1.0: both are apiResource(...)->except(['show']).
    expect(showOperation('household/chores'))->toBeNull()
        ->and(showOperation('household/shopping-list'))->toBeNull()
        ->and(showOperation('payroll/payslips'))->toBe('GET /payroll/payslips/{id}');
});

it('keeps ResourceQuery to builders and GET {path} terminals', function () {
    // query() is checked above against GET {path} only, so nothing on the
    // query it returns may reach another route. find() used to: it read
    // {path}/{id}, so chores()->query()->find(1) could call a show route the
    // platform does not have. A new public method here must read GET {path}
    // too (through fetch()); add it to this list once it does.
    $public = array_map(
        static fn (ReflectionMethod $method): string => $method->getName(),
        Codebase::ownPublicMethods(ResourceQuery::class),
    );

    sort($public);

    expect($public)->toBe(['collect', 'filter', 'first', 'get', 'include', 'lazy', 'page', 'params', 'perPage', 'sort']);
});
