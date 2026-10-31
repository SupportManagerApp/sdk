<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Hydration\Attributes;

use Attribute;

/**
 * Read a permission flag from the resource's JSON:API `meta.can` block, e.g.
 * `#[Can('withdraw')] public ?bool $canWithdraw` reads `meta.can.withdraw`.
 *
 * {@see \SupportManager\Sdk\Http\ResourceObject::flat()} carries the
 * resource-level `meta` under a `meta` key, so a DTO's single-argument
 * `fromArray()` sees it. Declare the parameter `bool` for "false unless the
 * platform says so" or `?bool` to tell "not sent" (null) apart.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class Can implements Source
{
    public function __construct(
        public string $ability,
    ) {}

    public function paths(): array
    {
        return ['meta.can.'.$this->ability];
    }
}
