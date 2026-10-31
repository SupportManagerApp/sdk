<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Unit\Hydration\Fixtures;

use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;

final readonly class Nested
{
    /**
     * @param  list<Child>  $children
     * @param  list<PlainChild>  $plainChildren
     * @param  list<string>  $tags
     * @param  list<int>|null  $ids
     * @param  list<float>  $scores
     * @param  list<bool>  $switches
     * @param  list<array<array-key, mixed>>  $rows
     * @param  list<Colour>  $colours
     * @param  list<Priority>  $priorities
     */
    public function __construct(
        public ?Child $child,
        public Child $requiredChild,
        public ?PlainChild $plain,
        public PlainChild $requiredPlain,
        #[ListOf(Child::class)]
        public array $children,
        #[ListOf(PlainChild::class)]
        public array $plainChildren,
        #[ListOf('string')]
        public array $tags,
        #[ListOf('int')]
        public ?array $ids,
        #[ListOf('float')]
        public array $scores,
        #[ListOf('bool')]
        public array $switches,
        #[ListOf('array')]
        public array $rows,
        #[ListOf(Colour::class)]
        public array $colours,
        #[ListOf(Priority::class)]
        public array $priorities,
    ) {}
}
