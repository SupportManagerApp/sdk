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
 */
final readonly class ResourceObject
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $relationships
     */
    public function __construct(
        public ?string $type,
        public ?string $id,
        public array $attributes,
        public array $relationships = [],
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
        );
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * The resource as one associative array — `id` and `type` merged in alongside
     * the attributes — ready to hand to a DTO's `fromArray()`.
     *
     * @return array<string, mixed>
     */
    public function flat(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            ...$this->attributes,
        ];
    }
}
