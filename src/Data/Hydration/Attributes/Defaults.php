<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Hydration\Attributes;

use Attribute;

/**
 * The value to hydrate when the key is missing or null, for a parameter whose
 * constructor has no default of its own, e.g. `#[Defaults('full')]` or
 * `#[Defaults(DevicePlatform::Ios)]`.
 *
 * The default is cast like any input value, so a backing value works for an
 * enum as well as a case. It only covers a missing value: an unknown value for
 * a non-nullable enum still throws, as `BackedEnum::from()` does.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class Defaults
{
    public function __construct(
        public mixed $value,
    ) {}
}
