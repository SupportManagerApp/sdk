<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Unit\Hydration\Fixtures;

/**
 * A nested DTO with its own fromArray(), which the hydrator must call.
 */
final readonly class Child
{
    public function __construct(
        public string $name,
        public bool $viaFromArray,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self((string) ($data['name'] ?? ''), true);
    }
}
