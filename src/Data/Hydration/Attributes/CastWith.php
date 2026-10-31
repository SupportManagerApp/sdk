<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Hydration\Attributes;

use Attribute;
use SupportManager\Sdk\Data\Hydration\Caster;

/**
 * Hand a parameter's value to a {@see Caster} instead of casting it by type —
 * the escape hatch for a field whose rule is reusable but not worth teaching
 * the hydrator, e.g. `#[CastWith(EmptyStringAsNull::class)]`.
 *
 * For a one-off rule, pass the value in the hydrator's `$overrides` from a
 * small custom `fromArray()` instead.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class CastWith
{
    /**
     * @param  class-string<Caster>  $caster
     */
    public function __construct(
        public string $caster,
    ) {}
}
