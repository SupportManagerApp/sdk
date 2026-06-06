<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Http;

use Closure;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * A single page of hydrated resources plus the JSON:API `meta`/`links` that
 * describe the wider collection.
 *
 * Iterable and countable so callers can `foreach` the items directly, while
 * {@see self::next()} walks to the following page by re-running the originating
 * query — the host app never has to assemble page URLs or query strings itself.
 *
 * @template T
 *
 * @implements IteratorAggregate<int, T>
 */
final readonly class Paginated implements Countable, IteratorAggregate
{
    /**
     * @param  list<T>  $items
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $links
     * @param  (Closure(int): Paginated<T>)|null  $pageFetcher  Fetches a page by number.
     */
    public function __construct(
        private array $items,
        private array $meta = [],
        private array $links = [],
        private ?Closure $pageFetcher = null,
    ) {}

    /**
     * @return list<T>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    /**
     * @return array<string, mixed>
     */
    public function links(): array
    {
        return $this->links;
    }

    public function currentPage(): ?int
    {
        return isset($this->meta['current_page']) ? (int) $this->meta['current_page'] : null;
    }

    public function lastPage(): ?int
    {
        return isset($this->meta['last_page']) ? (int) $this->meta['last_page'] : null;
    }

    public function perPage(): ?int
    {
        return isset($this->meta['per_page']) ? (int) $this->meta['per_page'] : null;
    }

    public function total(): ?int
    {
        return isset($this->meta['total']) ? (int) $this->meta['total'] : null;
    }

    /**
     * Whether a following page exists — preferring the `links.next` hint, falling
     * back to comparing the current and last page numbers.
     */
    public function hasMorePages(): bool
    {
        if (array_key_exists('next', $this->links)) {
            return $this->links['next'] !== null;
        }

        $current = $this->currentPage();
        $last = $this->lastPage();

        return $current !== null && $last !== null && $current < $last;
    }

    /**
     * The next page, or null when this is the last page (or the result was not
     * built from a re-runnable query).
     *
     * @return Paginated<T>|null
     */
    public function next(): ?self
    {
        $current = $this->currentPage();

        if ($this->pageFetcher === null || $current === null || ! $this->hasMorePages()) {
            return null;
        }

        return ($this->pageFetcher)($current + 1);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return Traversable<int, T>
     */
    public function getIterator(): Traversable
    {
        yield from $this->items;
    }
}
