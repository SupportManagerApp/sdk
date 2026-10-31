<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Unit\Hydration\Fixtures;

use SupportManager\Sdk\Data\Hydration\Attributes\Defaults;

final readonly class Enums
{
    public function __construct(
        public ?Colour $colour,
        #[Defaults(Colour::Red)]
        public Colour $withCaseDefault,
        #[Defaults('blue')]
        public Colour $withValueDefault,
        public ?Priority $priority,
        public Colour $strict = Colour::Blue,
    ) {}
}
