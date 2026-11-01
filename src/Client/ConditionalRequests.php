<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Client;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SupportManager\Sdk\Contracts\ResponseCache;

/**
 * Guzzle middleware for conditional GETs against a {@see ResponseCache}; see
 * that interface for the behaviour. {@see AuthenticatedApi} adds it only when
 * the host has bound a cache and the session knows its user.
 *
 * @internal
 */
final class ConditionalRequests
{
    public static function middleware(ResponseCache $cache, string $userId): Closure
    {
        return static fn (callable $handler): Closure => static function (RequestInterface $request, array $options) use ($handler, $cache, $userId): PromiseInterface {
            if ($request->getMethod() !== 'GET') {
                return $handler($request, $options);
            }

            $key = hash('sha256', implode("\n", [$userId, $request->getHeaderLine('X-Team-ID'), (string) $request->getUri()]));
            $cached = $cache->get($key);

            if ($cached !== null) {
                $request = $request->withHeader('If-None-Match', $cached->etag);
            }

            /** @var PromiseInterface $promise */
            $promise = $handler($request, $options);

            return $promise->then(static function (ResponseInterface $response) use ($cache, $key, $cached): ResponseInterface {
                if ($response->getStatusCode() === 304 && $cached !== null) {
                    return new Psr7Response(200, [
                        'Content-Type' => $cached->contentType,
                        'ETag' => $cached->etag,
                    ], $cached->body);
                }

                $etag = $response->getHeaderLine('ETag');
                $contentType = $response->getHeaderLine('Content-Type');

                if ($response->getStatusCode() !== 200 || $etag === '' || ! str_contains($contentType, 'json')) {
                    return $response;
                }

                $body = (string) $response->getBody();
                $cache->put($key, new CachedResponse($etag, $body, $contentType));

                return $response->withBody(Utils::streamFor($body));
            });
        };
    }
}
