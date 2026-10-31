<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Unit\Hydration\Fixtures;

use SupportManager\Sdk\Data\Hydration\Attributes\Can;
use SupportManager\Sdk\Data\Hydration\Attributes\CastWith;
use SupportManager\Sdk\Data\Hydration\Attributes\Defaults;
use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Attributes\Meta;
use SupportManager\Sdk\Data\Hydration\Casts\EmptyStringAsNull;

final readonly class Attributed
{
    public function __construct(
        #[From('user.name')]
        public ?string $userName,
        #[From('user.profile.age')]
        public ?int $userAge,
        #[From('legacy_title')]
        public string $title,
        #[From('total', 'amount')]
        public float $total,
        #[Can('withdraw')]
        public ?bool $canWithdraw,
        #[Can('delete')]
        public bool $canDelete,
        #[Meta('reason')]
        public ?string $reason,
        #[Defaults('full')]
        public string $width,
        #[Defaults(1)]
        public int $page,
        #[CastWith(EmptyStringAsNull::class)]
        public ?string $county,
        #[CastWith(Upper::class)]
        public string $shout,
        public string $mode = 'auto',
        public ?int $limit = 25,
    ) {}
}
