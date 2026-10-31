<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Concerns;

use SupportManager\Sdk\Http\Paginated;
use SupportManager\Sdk\Http\ResourceQuery;
use SupportManager\Sdk\Resources\Resource;

/**
 * `query()` and `all()` over the resource's collection, `GET {path}`.
 *
 * Opt-in: use it only on a resource whose platform route lists a JSON:API
 * collection at its {@see Resource::path()} and takes the Spatie QueryBuilder
 * grammar (`filter[key]`, `sort`, `page`, `per_page`). A resource whose index
 * is a singleton, a plain `{data}` list or a differently shaped query writes
 * its own method instead. `tests/Arch/CollectionReadsTest.php` checks every
 * resource that has `query()` or `all()` against the spec.
 *
 * ```php
 * final class ChoresResource extends Resource
 * {
 *     // PHPStan: `@use Listable<Chore>` in a docblock on the use line.
 *     use Listable;
 * }
 * ```
 *
 * @template T
 *
 * @phpstan-require-extends Resource
 */
trait Listable
{
    /**
     * Start a fluent query against the collection.
     *
     * @return ResourceQuery<T>
     */
    public function query(): ResourceQuery
    {
        return $this->newQuery();
    }

    /**
     * The first page of the collection.
     *
     * @return Paginated<T>
     */
    public function all(): Paginated
    {
        return $this->newQuery()->get();
    }
}
