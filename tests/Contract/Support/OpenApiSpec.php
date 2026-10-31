<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Contract\Support;

use cebe\openapi\spec\OpenApi;
use LogicException;
use Symfony\Component\Yaml\Yaml;

/**
 * The vendored platform spec (tests/Contract/openapi.yaml), parsed once per
 * process and prepared for strict validation.
 *
 * Each operation is turned into its own self-contained document: every `$ref`
 * is inlined, `allOf` is flattened into one schema, and every object schema
 * that lists its `properties` without saying otherwise gets
 * `additionalProperties: false`. That last step is what makes the check
 * useful. The spec never sets it, so without it a fixture could carry any
 * invented field (or the wrong envelope entirely) and still pass.
 *
 * Allowlist entries ({@see Allowlist}) are applied as narrow schema patches to
 * the one operation they name, so they cannot hide drift anywhere else.
 */
final class OpenApiSpec
{
    public const string PATH = __DIR__.'/../openapi.yaml';

    /** A Laravel error body, for error statuses an operation does not document. */
    public const array FALLBACK_ERROR_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'message' => ['type' => 'string'],
            'errors' => ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']]],
        ],
        'required' => ['message'],
        'additionalProperties' => false,
    ];

    private static ?self $instance = null;

    /** @var array<string, mixed> */
    private array $raw;

    /** @var array<string, array{method: string, template: string, regex: string, params: int}> */
    private array $operations = [];

    /** @var array<string, array<string, mixed>> Inlined, strict operation objects by key. */
    private array $prepared = [];

    /** @var array<string, OpenApi> */
    private array $documents = [];

    private function __construct()
    {
        /** @var array<string, mixed> $raw */
        $raw = Yaml::parseFile(self::PATH);
        $this->raw = $raw;

        /** @var array<string, array<string, mixed>> $paths */
        $paths = $raw['paths'] ?? [];

        foreach ($paths as $template => $item) {
            foreach (['get', 'put', 'post', 'delete', 'patch', 'head', 'options'] as $method) {
                if (! isset($item[$method])) {
                    continue;
                }

                $key = mb_strtoupper($method).' '.$template;
                $this->operations[$key] = [
                    'method' => mb_strtoupper($method),
                    'template' => (string) $template,
                    'regex' => '#^'.preg_replace('/\\\\\{[^}]+\\\\\}/', '[^/]+', preg_quote((string) $template, '#')).'$#',
                    'params' => mb_substr_count((string) $template, '{'),
                ];
            }
        }
    }

    public static function instance(): self
    {
        return self::$instance ??= new self;
    }

    /**
     * The platform commit the vendored spec was generated from.
     */
    public static function platformCommit(): string
    {
        $head = (string) file_get_contents(self::PATH, length: 2048);

        return preg_match('/^# platform-commit: (\S+)/m', $head, $m) === 1 ? $m[1] : 'unknown';
    }

    /**
     * Flatten `allOf` and close every object schema that lists its properties.
     *
     * @param  array<array-key, mixed>  $schema
     * @return array<array-key, mixed>
     */
    public static function strict(array $schema): array
    {
        if (isset($schema['allOf']) && is_array($schema['allOf'])) {
            $members = $schema['allOf'];
            unset($schema['allOf']);

            foreach ($members as $member) {
                $member = self::strict($member);

                $schema['properties'] = [...($schema['properties'] ?? []), ...($member['properties'] ?? [])];
                $schema['required'] = array_values(array_unique([...($schema['required'] ?? []), ...($member['required'] ?? [])]));

                unset($member['properties'], $member['required']);
                $schema += $member;
            }

            if ($schema['properties'] === []) {
                unset($schema['properties']);
            }

            if ($schema['required'] === []) {
                unset($schema['required']);
            }
        }

        foreach (['anyOf', 'oneOf'] as $keyword) {
            if (isset($schema[$keyword]) && is_array($schema[$keyword])) {
                $schema[$keyword] = array_map(self::strict(...), $schema[$keyword]);
            }
        }

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            $schema['properties'] = array_map(self::strict(...), $schema['properties']);

            if (! array_key_exists('additionalProperties', $schema)) {
                $schema['additionalProperties'] = false;
            }
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = self::strict($schema['items']);
        }

        if (isset($schema['additionalProperties']) && is_array($schema['additionalProperties'])) {
            $schema['additionalProperties'] = self::strict($schema['additionalProperties']);
        }

        return $schema;
    }

    /**
     * Every operation key, e.g. `GET /accidents/{accidentReport}`.
     *
     * @return list<string>
     */
    public function operationKeys(): array
    {
        return array_keys($this->operations);
    }

    public function has(string $operation): bool
    {
        return isset($this->operations[$operation]);
    }

    /**
     * The operation key for a concrete request, preferring the most literal
     * template (`/car-journeys/ongoing` over `/car-journeys/{id}`), or null when
     * the spec has no such operation.
     */
    public function match(string $method, string $path): ?string
    {
        $best = null;

        foreach ($this->operations as $key => $operation) {
            if ($operation['method'] !== $method || preg_match($operation['regex'], $path) !== 1) {
                continue;
            }

            if ($best === null || $operation['params'] < $this->operations[$best]['params']) {
                $best = $key;
            }
        }

        return $best;
    }

    /**
     * Whether the spec has the path under any method, to tell a wrong method
     * from an unknown path in failure messages.
     *
     * @return list<string>
     */
    public function methodsFor(string $path): array
    {
        $methods = [];

        foreach ($this->operations as $operation) {
            if (preg_match($operation['regex'], $path) === 1) {
                $methods[] = $operation['method'];
            }
        }

        return $methods;
    }

    public function template(string $operation): string
    {
        return $this->operations[$operation]['template'];
    }

    public function method(string $operation): string
    {
        return $this->operations[$operation]['method'];
    }

    /**
     * The statuses the operation documents.
     *
     * @return list<string>
     */
    public function statuses(string $operation): array
    {
        /** @var array<array-key, mixed> $responses */
        $responses = $this->prepare($operation)['responses'] ?? [];

        return array_map(strval(...), array_keys($responses));
    }

    /**
     * A one-operation OpenAPI document for the validator, with the given
     * allowlist patches applied. Cached by operation and patch set.
     *
     * @param  list<AllowlistEntry>  $patches
     */
    public function document(string $operation, array $patches = []): OpenApi
    {
        $cacheKey = $operation.'|'.implode(',', array_map(static fn (AllowlistEntry $entry): string => $entry->id, $patches));

        if (isset($this->documents[$cacheKey])) {
            return $this->documents[$cacheKey];
        }

        $op = $this->prepare($operation);

        foreach ($patches as $patch) {
            $op = $patch->applyTo($op);
        }

        // An error status the operation does not document is still a Laravel
        // error body (see the README's "Contract tests" section).
        foreach (['4XX', '5XX'] as $range) {
            $op['responses'][$range] ??= [
                'description' => 'Undocumented error (SDK contract-test fallback)',
                'content' => ['application/json' => ['schema' => self::FALLBACK_ERROR_SCHEMA]],
            ];
        }

        $template = $this->template($operation);
        $item = [mb_strtolower($this->method($operation)) => $op];

        return $this->documents[$cacheKey] = new OpenApi([
            'openapi' => '3.0.3',
            'info' => ['title' => $operation, 'version' => '1'],
            'components' => ['securitySchemes' => $this->raw['components']['securitySchemes'] ?? []],
            'security' => $this->raw['security'] ?? [],
            'paths' => [$template => $item],
        ]);
    }

    /**
     * The inlined, strict operation object.
     *
     * @return array<string, mixed>
     */
    public function prepare(string $operation): array
    {
        if (isset($this->prepared[$operation])) {
            return $this->prepared[$operation];
        }

        if (! $this->has($operation)) {
            throw new LogicException("The spec has no operation {$operation}.");
        }

        /** @var array<string, mixed> $pathItem */
        $pathItem = $this->raw['paths'][$this->template($operation)];

        /** @var array<string, mixed> $op */
        $op = $this->inline($pathItem[mb_strtolower($this->method($operation))]);

        // Fold path-level parameters into the operation (the operation's own
        // win), so allowlist entries only have one place to patch.
        $parameters = [];

        foreach ([...$this->inline($pathItem['parameters'] ?? []), ...($op['parameters'] ?? [])] as $parameter) {
            $parameters[($parameter['in'] ?? '').':'.($parameter['name'] ?? '')] = $parameter;
        }

        if ($parameters !== []) {
            $op['parameters'] = array_values($parameters);
        }

        if (isset($op['requestBody']['content']) && is_array($op['requestBody']['content'])) {
            foreach ($op['requestBody']['content'] as $type => $media) {
                if (isset($media['schema'])) {
                    $op['requestBody']['content'][$type]['schema'] = self::strict($media['schema']);
                }
            }
        }

        $responses = [];

        foreach ($op['responses'] ?? [] as $status => $response) {
            foreach ($response['content'] ?? [] as $type => $media) {
                if (isset($media['schema'])) {
                    $response['content'][$type]['schema'] = self::strict($media['schema']);
                }
            }

            $responses[(string) $status] = $response;
        }

        $op['responses'] = $responses;

        return $this->prepared[$operation] = $op;
    }

    /**
     * Replace every local `$ref` with a copy of its target.
     *
     * @param  list<string>  $stack
     */
    private function inline(mixed $node, array $stack = []): mixed
    {
        if (! is_array($node)) {
            return $node;
        }

        if (isset($node['$ref']) && is_string($node['$ref'])) {
            $ref = $node['$ref'];

            if (in_array($ref, $stack, true)) {
                throw new LogicException("The spec has a recursive \$ref ({$ref}); contract tests do not support those yet.");
            }

            return $this->inline($this->resolve($ref), [...$stack, $ref]);
        }

        foreach ($node as $key => $value) {
            $node[$key] = $this->inline($value, $stack);
        }

        return $node;
    }

    private function resolve(string $ref): mixed
    {
        if (! str_starts_with($ref, '#/')) {
            throw new LogicException("The spec has an external \$ref ({$ref}); regenerate it with composer sync-openapi.");
        }

        $node = $this->raw;

        foreach (explode('/', mb_substr($ref, 2)) as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);

            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                throw new LogicException("The spec has a broken \$ref ({$ref}).");
            }

            $node = $node[$segment];
        }

        return $node;
    }
}
