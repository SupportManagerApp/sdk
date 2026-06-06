<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing;

/**
 * What the fake knows about one seeded resource: its endpoint path, its wire
 * dialect (the JSON:API type, or null for a flat endpoint), and the custom
 * routes that take precedence over generic REST.
 */
final readonly class Registration
{
    /**
     * @param  list<FakeRoute>  $routes
     */
    public function __construct(
        public string $path,
        public ?string $type,
        public array $routes,
    ) {}
}
