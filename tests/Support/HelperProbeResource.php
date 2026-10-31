<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Support;

use Closure;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\Response;
use LogicException;
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
 * Its requests never reach the HTTP client: each is answered from the canned
 * responses given to {@see self::respond()}, keyed by path plus query string.
 * That keeps the made-up `probe/*` paths off the faked wire, where the
 * OpenAPI contract check would reject them.
 *
 * @extends \SupportManager\Sdk\Resources\Resource<Team>
 */
final class HelperProbeResource extends Resource
{
    /** @var list<array{method: string, uri: string}> */
    public array $sent = [];

    /** @var array<string, Response> */
    private array $responses = [];

    /**
     * A response with transfer stats for `https://api.test/api/v1/{$uri}`, so
     * {@see Response::effectiveUri()} reports a path as a real request would.
     *
     * @param  array<string, string>  $headers
     */
    public static function response(string $uri, mixed $body = [], int $status = 200, array $headers = []): Response
    {
        $isJson = is_array($body);
        $psr = new Psr7Response(
            $status,
            $isJson ? ['Content-Type' => 'application/json', ...$headers] : $headers,
            $isJson ? (string) json_encode($body) : (string) $body,
        );

        $response = new Response($psr);
        $response->transferStats = new TransferStats(new Request('GET', new Uri("https://api.test/api/v1/{$uri}")), $psr);

        return $response;
    }

    /**
     * Answer requests from canned bodies, keyed by path (plus `?query`).
     *
     * @param  array<string, Response>  $responses
     */
    public function respond(array $responses): self
    {
        $this->responses = $responses;

        return $this;
    }

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

    /**
     * @param  array<string, mixed>  $query
     */
    protected function get(string $uri, array $query = []): Response
    {
        return $this->answer('GET', $query === [] ? $uri : $uri.'?'.http_build_query($query));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function delete(string $uri, array $data = []): Response
    {
        return $this->answer('DELETE', $uri);
    }

    protected function path(): string
    {
        return 'probe';
    }

    protected function hydrate(ResourceObject $object): Team
    {
        return Team::fromArray($object->flat());
    }

    private function answer(string $method, string $uri): Response
    {
        $this->sent[] = ['method' => $method, 'uri' => $uri];

        return $this->responses[$uri] ?? throw new LogicException("No canned response for {$method} {$uri}.");
    }
}
