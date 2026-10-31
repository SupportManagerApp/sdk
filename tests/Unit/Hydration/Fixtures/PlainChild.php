<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Unit\Hydration\Fixtures;

/**
 * A nested DTO without fromArray(), which the hydrator hydrates recursively.
 */
final readonly class PlainChild
{
    public function __construct(
        public int $size,
        public ?string $label,
    ) {}
}
