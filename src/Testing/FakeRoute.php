<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing;

use Closure;
use Illuminate\Http\Client\Request;

/**
 * One fake server route — a method + path pattern bound to a handler that
 * answers it against the {@see RecordStore}.
 *
 * A resource registers these (via {@see ProvidesFakeRoutes}) for the endpoints
 * the generic REST machinery can't infer: non-RESTful actions
 * (`car-journeys/start`, `notifications/{id}/read`), flat-singleton reveals, or
 * index endpoints with bespoke filtering. Matched routes are tried before the
 * generic CRUD fallback, so they take precedence.
 *
 * Patterns are written against the `/api/v1`-relative path with `{id}`-style
 * placeholders, e.g. `car-journeys/{id}/finish`; captured segments arrive in the
 * handler's `$matches` (1-indexed, as from `preg_match`). A handler returns an
 * `Http::response(...)` to answer, or null to fall through to the next route.
 */
final class FakeRoute
{
    /**
     * @param  string  $method  Upper-case HTTP method.
     * @param  string  $regex  Compiled full-match regex for the relative path.
     * @param  Closure(RecordStore, array<int, string>, Request, Fake): mixed  $handler
     */
    public function __construct(
        public readonly string $method,
        public readonly string $regex,
        public readonly Closure $handler,
    ) {}

    /**
     * @param  Closure(RecordStore, array<int, string>, Request, Fake): mixed  $handler
     */
    public static function get(string $pattern, Closure $handler): self
    {
        return self::make('GET', $pattern, $handler);
    }

    /**
     * @param  Closure(RecordStore, array<int, string>, Request, Fake): mixed  $handler
     */
    public static function post(string $pattern, Closure $handler): self
    {
        return self::make('POST', $pattern, $handler);
    }

    /**
     * @param  Closure(RecordStore, array<int, string>, Request, Fake): mixed  $handler
     */
    public static function patch(string $pattern, Closure $handler): self
    {
        return self::make('PATCH', $pattern, $handler);
    }

    /**
     * @param  Closure(RecordStore, array<int, string>, Request, Fake): mixed  $handler
     */
    public static function put(string $pattern, Closure $handler): self
    {
        return self::make('PUT', $pattern, $handler);
    }

    /**
     * @param  Closure(RecordStore, array<int, string>, Request, Fake): mixed  $handler
     */
    public static function delete(string $pattern, Closure $handler): self
    {
        return self::make('DELETE', $pattern, $handler);
    }

    /**
     * The captured path segments when this route answers the given method and
     * path (element 0 is the whole match, as from `preg_match`), or null when it
     * does not.
     *
     * @return array<int, string>|null
     */
    public function match(string $method, string $path): ?array
    {
        if ($method !== $this->method) {
            return null;
        }

        $matches = [];

        return preg_match($this->regex, $path, $matches) === 1 ? $matches : null;
    }

    /**
     * @param  Closure(RecordStore, array<int, string>, Request, Fake): mixed  $handler
     */
    private static function make(string $method, string $pattern, Closure $handler): self
    {
        // Quote the literal path, then swap each escaped `\{name\}` placeholder
        // for a single-segment capture group.
        $escaped = preg_quote($pattern, '#');
        $regex = preg_replace('/\\\\\{[^}]*\\\\\}/', '([^/]+)', $escaped) ?? $escaped;

        return new self($method, '#^'.$regex.'$#', $handler);
    }
}
