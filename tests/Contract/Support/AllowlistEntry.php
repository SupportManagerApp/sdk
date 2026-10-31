<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Contract\Support;

use InvalidArgumentException;

/**
 * One reasoned exception to the spec, scoped to one operation, one place (the
 * request body, a query or path parameter, or one response status) and one
 * JSON pointer.
 *
 * It is applied as a patch to that operation's schema, describing what the
 * platform really does at that pointer:
 *  - `schema`: the real schema of the value (adds the property when the spec
 *    leaves it out);
 *  - `nullable`: the value may be null;
 *  - `optional`: the value may be absent (removed from `required`).
 *
 * Pointers name the body location, with `*` for "every array item", e.g.
 * `/data/* /attributes/is_provisional` (without the space). For a query or
 * path parameter the pointer is `/<name>`, e.g. `/filter[status]`. A pointer
 * of '' with a `schema` on a response status the spec leaves out adds that
 * status.
 */
final class AllowlistEntry
{
    /**
     * @param  'request'|'query'|'path'|int  $in
     * @param  list<string>  $segments
     * @param  array<array-key, mixed>|null  $schema
     */
    public function __construct(
        public readonly string $id,
        public readonly string $source,
        public readonly string $operation,
        public readonly string|int $in,
        public readonly string $pointer,
        public readonly array $segments,
        public readonly ?array $schema,
        public readonly bool $nullable,
        public readonly bool $optional,
        public readonly string $reason,
    ) {}

    /**
     * @param  array<string, mixed>  $entry
     */
    public static function fromArray(string $source, int $index, array $entry): self
    {
        $where = "{$source}[{$index}]";

        foreach (['operation', 'in', 'pointer', 'reason'] as $key) {
            if (! isset($entry[$key]) || $entry[$key] === '') {
                throw new InvalidArgumentException("{$where} needs a non-empty '{$key}'.");
            }
        }

        $in = $entry['in'];

        if (! in_array($in, ['request', 'query', 'path'], true) && ! is_int($in)) {
            throw new InvalidArgumentException("{$where}: 'in' is 'request', 'query', 'path' or a response status code.");
        }

        $pointer = (string) $entry['pointer'];

        if ($pointer !== '' && ! str_starts_with($pointer, '/')) {
            throw new InvalidArgumentException("{$where}: 'pointer' must be a JSON pointer starting with '/'.");
        }

        $schema = $entry['schema'] ?? null;
        $nullable = (bool) ($entry['nullable'] ?? false);
        $optional = (bool) ($entry['optional'] ?? false);

        if ($schema === null && ! $nullable && ! $optional) {
            throw new InvalidArgumentException("{$where} must set 'schema', 'nullable' or 'optional'.");
        }

        $segments = $pointer === '' ? [] : array_map(
            static fn (string $segment): string => str_replace(['~1', '~0'], ['/', '~'], $segment),
            explode('/', mb_substr($pointer, 1)),
        );

        return new self(
            id: "{$source}#{$index}",
            source: $source,
            operation: (string) $entry['operation'],
            in: $in,
            pointer: $pointer,
            segments: $segments,
            schema: is_array($schema) ? $schema : null,
            nullable: $nullable,
            optional: $optional,
            reason: (string) $entry['reason'],
        );
    }

    public function describe(): string
    {
        $in = match ($this->in) {
            'request' => 'request body',
            'query' => 'query parameter',
            'path' => 'path parameter',
            default => "{$this->in} response",
        };

        return "{$this->operation} {$in} {$this->pointer}: {$this->reason}";
    }

    /**
     * Patch the inlined operation object.
     *
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    public function applyTo(array $operation): array
    {
        if ($this->in === 'query' || $this->in === 'path') {
            return $this->applyToParameter($operation);
        }

        if (is_int($this->in) && $this->segments === [] && $this->schema !== null && ! isset($operation['responses'][(string) $this->in])) {
            // A status the spec leaves out entirely.
            $operation['responses'][(string) $this->in] = [
                'description' => "Undocumented (allowlisted: {$this->id})",
                'content' => ['application/json' => ['schema' => OpenApiSpec::strict($this->schema)]],
            ];

            return $operation;
        }

        if ($this->in === 'request') {
            $content = $operation['requestBody']['content'] ?? null;
        } else {
            $content = $operation['responses'][(string) $this->in]['content'] ?? null;
        }

        if (! is_array($content) || $content === []) {
            throw new InvalidArgumentException("{$this->id} ({$this->describe()}) names a body the spec does not document.");
        }

        $patched = false;

        foreach ($content as $type => $media) {
            if (! isset($media['schema']) || ! is_array($media['schema'])) {
                continue;
            }

            $schema = $media['schema'];

            if ($this->walk($schema, $this->segments)) {
                $content[$type]['schema'] = $schema;
                $patched = true;
            }
        }

        if (! $patched) {
            throw new InvalidArgumentException("{$this->id} ({$this->describe()}) no longer matches the spec's schema; update or remove it.");
        }

        if ($this->in === 'request') {
            $operation['requestBody']['content'] = $content;
        } else {
            $operation['responses'][(string) $this->in]['content'] = $content;
        }

        return $operation;
    }

    /**
     * Patch (or add) the query or path parameter the pointer names.
     *
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function applyToParameter(array $operation): array
    {
        if (count($this->segments) !== 1) {
            throw new InvalidArgumentException("{$this->id}: a {$this->in} entry's pointer is '/<parameter name>'.");
        }

        $name = $this->segments[0];
        $parameters = $operation['parameters'] ?? [];

        foreach ($parameters as $i => $parameter) {
            if (($parameter['in'] ?? null) === $this->in && ($parameter['name'] ?? null) === $name) {
                if ($this->optional) {
                    $parameters[$i]['required'] = false;
                }

                $schema = $parameter['schema'] ?? [];
                $this->patchValue($schema);
                $parameters[$i]['schema'] = $schema;
                $operation['parameters'] = $parameters;

                return $operation;
            }
        }

        if ($this->schema === null) {
            throw new InvalidArgumentException("{$this->id} ({$this->describe()}) names a {$this->in} parameter the spec does not document; give its 'schema' to add it.");
        }

        $operation['parameters'] = [...$parameters, [
            'name' => $name,
            'in' => $this->in,
            'required' => $this->in === 'path',
            'schema' => $this->schema,
        ]];

        return $operation;
    }

    /**
     * Walk the schema along the pointer and patch the final node. Returns
     * whether the pointer could be followed.
     *
     * @param  array<array-key, mixed>  $node
     * @param  list<string>  $segments
     */
    private function walk(array &$node, array $segments): bool
    {
        foreach (['anyOf', 'oneOf'] as $keyword) {
            if (isset($node[$keyword]) && is_array($node[$keyword])) {
                $any = false;

                foreach ($node[$keyword] as $i => $member) {
                    if ($this->walk($member, $segments)) {
                        $node[$keyword][$i] = $member;
                        $any = true;
                    }
                }

                return $any;
            }
        }

        if ($segments === []) {
            $this->patchValue($node);

            return true;
        }

        $segment = array_shift($segments);

        if ($segment === '*') {
            if (! isset($node['items']) || ! is_array($node['items'])) {
                return false;
            }

            return $this->walk($node['items'], $segments);
        }

        if ($segments === []) {
            // The last segment names a property of this object.
            if (! isset($node['properties']) && ! isset($node['type'])) {
                return false;
            }

            if ($this->optional) {
                $node['required'] = array_values(array_diff($node['required'] ?? [], [$segment]));

                if ($node['required'] === []) {
                    unset($node['required']);
                }
            }

            if (isset($node['properties'][$segment]) || $this->schema !== null) {
                $property = $node['properties'][$segment] ?? [];
                $this->patchValue($property);
                $node['properties'][$segment] = $property;
            } elseif (! $this->optional) {
                return false;
            }

            return true;
        }

        if (isset($node['properties'][$segment]) && is_array($node['properties'][$segment])) {
            return $this->walk($node['properties'][$segment], $segments);
        }

        if (isset($node['additionalProperties']) && is_array($node['additionalProperties'])) {
            return $this->walk($node['additionalProperties'], $segments);
        }

        return false;
    }

    /**
     * @param  array<array-key, mixed>  $node
     */
    private function patchValue(array &$node): void
    {
        if ($this->schema !== null) {
            $node = OpenApiSpec::strict($this->schema);
        }

        if ($this->nullable) {
            $node['nullable'] = true;
        }
    }
}
