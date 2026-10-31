<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Unit\Hydration\Fixtures;

final readonly class Scalars
{
    /**
     * @param  array<array-key, mixed>  $data
     * @param  array<array-key, mixed>|null  $extra
     */
    public function __construct(
        public string $id,
        public ?string $name,
        public int $count,
        public ?int $teamId,
        public float $amount,
        public ?float $rate,
        public bool $active,
        public ?bool $flag,
        public array $data,
        public ?array $extra,
        public mixed $anything,
        public bool $week1Month1,
    ) {}
}
