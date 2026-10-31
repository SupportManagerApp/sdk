<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Hydration\Attributes;

use Attribute;

/**
 * Read a parameter from the resource's JSON:API `meta` block, e.g.
 * `#[Meta('cannot_apply_reason')]` reads `meta.cannot_apply_reason`. Use
 * {@see Can} for `meta.can` permission flags.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class Meta implements Source
{
    public function __construct(
        public string $key,
    ) {}

    public function paths(): array
    {
        return ['meta.'.$this->key];
    }
}
