<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Http;

/**
 * A decoded JSON:API document — the top-level `{data, included, meta, links}`
 * envelope the platform returns for resource endpoints.
 *
 * `data` is a single resource object for item endpoints and a list for
 * collections; this wrapper normalises access to either and exposes the
 * pagination `meta`/`links` a {@see Paginated} result is built from. Auth
 * endpoints return a *flat* `data` payload instead and are intentionally not
 * routed through here — only tenant-scoped resource responses are JSON:API.
 */
final readonly class Document
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(private array $raw) {}

    /**
     * @param  array<string, mixed>  $raw  The decoded response body.
     */
    public static function fromArray(array $raw): self
    {
        return new self($raw);
    }

    /**
     * Whether `data` holds a list of resource objects rather than a single one.
     * An absent or empty `data` member counts as an (empty) collection.
     */
    public function isCollection(): bool
    {
        $data = $this->raw['data'] ?? [];

        return is_array($data) && array_is_list($data);
    }

    /**
     * The resource objects from a collection document.
     *
     * @return list<ResourceObject>
     */
    public function resources(): array
    {
        $data = $this->raw['data'] ?? [];

        if (! is_array($data) || $data === []) {
            return [];
        }

        if (! array_is_list($data)) {
            return [ResourceObject::fromArray($data)];
        }

        return array_values(array_map(
            static fn (mixed $item): ResourceObject => ResourceObject::fromArray(is_array($item) ? $item : []),
            $data,
        ));
    }

    /**
     * The single resource object from an item document, or null when `data` is
     * absent/empty.
     */
    public function resource(): ?ResourceObject
    {
        $data = $this->raw['data'] ?? null;

        if (! is_array($data) || $data === []) {
            return null;
        }

        if (array_is_list($data)) {
            return ResourceObject::fromArray(is_array($data[0]) ? $data[0] : []);
        }

        return ResourceObject::fromArray($data);
    }

    /**
     * Side-loaded resource objects from the document's `included` member.
     *
     * @return list<ResourceObject>
     */
    public function included(): array
    {
        $included = $this->raw['included'] ?? [];

        if (! is_array($included)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $item): ResourceObject => ResourceObject::fromArray(is_array($item) ? $item : []),
            $included,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->raw['meta'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function links(): array
    {
        return $this->raw['links'] ?? [];
    }
}
