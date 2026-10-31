<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Hydration\Attributes;

use Attribute;

/**
 * The element type of an `array` parameter, which PHP cannot declare itself:
 * `#[ListOf(Task::class)]` for `list<Task>`, or `#[ListOf('string')]` for
 * `list<string>`.
 *
 * The type is a DTO class, a backed enum, or one of `string`, `int`, `float`,
 * `bool` and `array`. Elements that cannot be that type are dropped rather than
 * hydrated into blanks: a DTO or `array` keeps only array elements, a scalar
 * type keeps only scalars (cast), an enum keeps only known values. The result
 * is always re-indexed as a list.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class ListOf
{
    /**
     * @param  class-string|'string'|'int'|'float'|'bool'|'array'  $type
     */
    public function __construct(
        public string $type,
    ) {}
}
