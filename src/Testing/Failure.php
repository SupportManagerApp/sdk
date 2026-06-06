<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing;

/**
 * A programmed failure for the fake — an error response (or a simulated
 * connection drop) for requests matching a method and path glob.
 *
 * Failures are checked before any seeded data, so a host can drive the unhappy
 * path (validation errors, 5xx, an unreachable API) through the SDK's real error
 * mapping without seeding anything. The path glob matches the `/api/v1`-relative
 * path, with `*` standing for any run of characters (e.g. `car-journeys*`,
 * `*`).
 */
final readonly class Failure
{
    /**
     * @param  string|null  $method  Upper-case HTTP method, or null for any.
     * @param  string  $regex  Compiled full-match regex for the relative path.
     * @param  bool  $connection  Simulate a transport-level connection failure.
     * @param  int  $status  HTTP status for a response failure.
     * @param  array<string, mixed>  $body  Response body for a response failure.
     */
    public function __construct(
        public ?string $method,
        public string $regex,
        public bool $connection,
        public int $status = 500,
        public array $body = [],
    ) {}

    /**
     * Compile a path glob (`*` = any characters) into a full-match regex.
     */
    public static function compile(string $glob): string
    {
        return '#^'.str_replace('\*', '.*', preg_quote($glob, '#')).'$#';
    }

    /**
     * Whether this failure applies to the given method and relative path.
     */
    public function matches(string $method, string $path): bool
    {
        if ($this->method !== null && $this->method !== $method) {
            return false;
        }

        return preg_match($this->regex, $path) === 1;
    }
}
