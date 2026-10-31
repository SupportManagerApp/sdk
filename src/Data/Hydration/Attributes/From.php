<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Hydration\Attributes;

use Attribute;

/**
 * Read a parameter from a renamed key or a dot-path into a nested object, e.g.
 * `#[From('user.name')]`.
 *
 * Several paths form a fallback chain: `#[From('taxable_gross', 'total_gross')]`
 * takes `taxable_gross` when it is set (non-null) and `total_gross` otherwise.
 * A path stops resolving as soon as a segment is missing, null or not an array.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class From implements Source
{
    /** @var non-empty-list<string> */
    private array $paths;

    public function __construct(string $path, string ...$fallbacks)
    {
        $this->paths = [$path, ...array_values($fallbacks)];
    }

    public function paths(): array
    {
        return $this->paths;
    }
}
