<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Support;

use Closure;
use stdClass;
use SupportManager\Sdk\Data\Tasks\Task;
use SupportManager\Sdk\Data\Teams\Team;
use SupportManager\Sdk\Http\Download;
use SupportManager\Sdk\Http\DownloadLink;
use SupportManager\Sdk\Http\Paginated;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * A throwaway resource that exposes the base class's protected readers, so
 * their parsing and error mapping can be tested without a real module. Its own
 * DTO is {@see Team}; {@see Task} stands in for "some other DTO".
 *
 * @extends \SupportManager\Sdk\Resources\Resource<Team>
 */
final class HelperProbeResource extends Resource
{
    public function itemFrom(string $uri): ?Team
    {
        return $this->item($this->get($uri));
    }

    public function itemAsTask(string $uri): ?Task
    {
        return $this->item($this->get($uri), Task::class);
    }

    /**
     * @return list<Team>
     */
    public function collectionFrom(string $uri): array
    {
        return $this->collection($this->get($uri));
    }

    /**
     * @return list<Task>
     */
    public function collectionAsTasks(string $uri): array
    {
        return $this->collection($this->get($uri), Task::class);
    }

    /**
     * @param  (Closure(int): Paginated<Team>)|null  $pageFetcher
     * @param  array<string, mixed>  $query
     * @return Paginated<Team>
     */
    public function paginatedFrom(string $uri, ?Closure $pageFetcher = null, array $query = []): Paginated
    {
        return $this->paginated($this->get($uri, $query), $pageFetcher);
    }

    public function dataFrom(string $uri): ?Task
    {
        return $this->data($this->get($uri), Task::class);
    }

    /**
     * @return list<Task>
     */
    public function dataListFrom(string $uri): array
    {
        return $this->dataList($this->get($uri), Task::class);
    }

    /**
     * @param  (Closure(int): Paginated<Task>)|null  $pageFetcher
     * @param  array<string, mixed>  $query
     * @return Paginated<Task>
     */
    public function dataPaginatedFrom(string $uri, ?Closure $pageFetcher = null, array $query = []): Paginated
    {
        return $this->dataPaginated($this->get($uri, $query), Task::class, $pageFetcher);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function rawFrom(string $uri): array
    {
        return $this->raw($this->get($uri));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function file(string $uri, array $query = []): Download
    {
        return $this->downloadFile($uri, $query);
    }

    public function link(string $uri): DownloadLink
    {
        return $this->downloadLink($uri);
    }

    public function noContent(string $uri): void
    {
        $this->throwForErrorResponse($this->delete($uri));
    }

    /**
     * Hydrate through a class with no `fromArray()`, which is a programming error.
     */
    public function dataAsUnhydratable(string $uri): ?object
    {
        return $this->data($this->get($uri), stdClass::class);
    }

    protected function path(): string
    {
        return 'probe';
    }

    protected function hydrate(ResourceObject $object): Team
    {
        return Team::fromArray($object->flat());
    }
}
