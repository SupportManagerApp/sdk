<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Contracts;

use SupportManager\Sdk\Client\CachedResponse;

/**
 * Where the SDK keeps API responses for conditional GETs. Opt-in: with
 * nothing bound, the SDK sends no `If-None-Match` and keeps nothing.
 *
 * Bind an implementation in the host's container to switch it on:
 *
 * ```php
 * $this->app->singleton(ResponseCache::class, ArrayResponseCache::class);
 * ```
 *
 * The SDK then remembers each successful JSON `GET`'s `ETag` and body. Next
 * time it sends `If-None-Match`, and when the platform answers
 * `304 Not Modified` it hands back the remembered body as an ordinary 200,
 * so callers can't tell the difference. That saves transfer, not the round
 * trip.
 *
 * Keys already separate people, teams and URLs (a hash of the user id,
 * `X-Team-ID` and the full URL), so entries never cross users. But they are
 * whole response bodies, personal data included. Choose a backing store that
 * suits that, and clear it on sign-out if it outlives the process. File
 * downloads are never cached.
 */
interface ResponseCache
{
    public function get(string $key): ?CachedResponse;

    public function put(string $key, CachedResponse $response): void;
}
