<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Household;

use SupportManager\Sdk\Data\Hydration\Hydrator;
use SupportManager\Sdk\Enums\Household\BinType;

/**
 * When one bin is next collected, from the platform's `GET household/bin-day`
 * (`data.collections`).
 *
 * `nextCollectionOn` is the `Y-m-d` collection day, or null when the council
 * cannot currently supply it (then `isDueTomorrow` is false).
 */
final readonly class BinCollection
{
    public function __construct(
        public BinType $bin,
        public string $label,
        public ?string $nextCollectionOn,
        public bool $isDueTomorrow,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data  An entry from `data.collections`.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
