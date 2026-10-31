<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Http;

/**
 * A single JSON:API resource object — the `{type, id, attributes, relationships}`
 * shape the platform emits via Laravel's native JSON:API resources.
 *
 * JSON:API splits the identifier out of the attribute bag and always serialises
 * it as a string. {@see self::flat()} folds `id` (and `type`) back in so a DTO's
 * `fromArray()` can read every field from one associative array, the same way it
 * would a flat payload.
 *
 * A resource-level `meta` member (e.g. the platform's `meta.can` permission
 * flags) rides along in {@see self::flat()} under a `meta` key when present.
 * That is the one way DTOs receive it: `fromArray()` always takes the single
 * flat array, and a DTO reads a flag with
 * {@see \SupportManager\Sdk\Data\Hydration\Attributes\Can} (`meta.can.*`) or
 * any other meta key with {@see \SupportManager\Sdk\Data\Hydration\Attributes\Meta}.
 * {@see self::$meta} stays available for callers that want the block itself.
 */
final readonly class ResourceObject
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $relationships
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public ?string $type,
        public ?string $id,
        public array $attributes,
        public array $relationships = [],
        public array $meta = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $data  A single element from a JSON:API `data` member.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: isset($data['type']) ? (string) $data['type'] : null,
            id: isset($data['id']) ? (string) $data['id'] : null,
            attributes: $data['attributes'] ?? [],
            relationships: $data['relationships'] ?? [],
            meta: is_array($data['meta'] ?? null) ? $data['meta'] : [],
        );
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * The resource as one associative array — `id` and `type` merged in alongside
     * the attributes, plus any resource-level `meta` under `meta` — ready to hand
     * to a DTO's `fromArray()`.
     *
     * @return array<string, mixed>
     */
    public function flat(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            ...$this->attributes,
            ...($this->meta === [] ? [] : ['meta' => $this->meta]),
        ];
    }
}
