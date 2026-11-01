<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Client;

use SupportManager\Sdk\Contracts\ResponseCache;

/**
 * A {@see ResponseCache} that lives in memory for the life of the process.
 * Bind it as a singleton for conditional GETs within one process, or use it
 * as the model for a persistent one.
 */
final class ArrayResponseCache implements ResponseCache
{
    /** @var array<string, CachedResponse> */
    private array $responses = [];

    public function get(string $key): ?CachedResponse
    {
        return $this->responses[$key] ?? null;
    }

    public function put(string $key, CachedResponse $response): void
    {
        $this->responses[$key] = $response;
    }

    /**
     * Forget everything, e.g. on sign-out.
     */
    public function flush(): void
    {
        $this->responses = [];
    }
}
