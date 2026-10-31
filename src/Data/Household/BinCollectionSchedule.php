<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Household;

use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;
use SupportManager\Sdk\Data\Hydration\Hydrator;
use SupportManager\Sdk\Enums\Household\BinType;

/**
 * Each bin's next collection and which bins go out tomorrow, from the
 * platform's `GET household/bin-day`: the data behind the `household.bin-day`
 * dashboard widget, which the web app shows only while
 * {@see self::hasBinsDueTomorrow()}.
 *
 * The platform names its bins `rubbish`, `recycling` and `food`, and says to
 * skip any other, so a collection or due bin of a type this SDK does not know
 * is left out of both lists.
 */
final readonly class BinCollectionSchedule
{
    /**
     * @param  list<BinType>  $binsDueTomorrow  In the order of `$collections`.
     * @param  list<BinCollection>  $collections  One per bin: rubbish, recycling, food.
     */
    public function __construct(
        #[ListOf(BinType::class)]
        public array $binsDueTomorrow,
        public array $collections,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The response `data` member.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data, [
            'collections' => self::knownCollections($data['collections'] ?? null),
        ]);
    }

    /**
     * Whether any bin goes out tomorrow, which is when the web widget shows.
     */
    public function hasBinsDueTomorrow(): bool
    {
        return $this->binsDueTomorrow !== [];
    }

    /**
     * The collection of one bin, or null when the platform sent none for it.
     */
    public function collectionOf(BinType $bin): ?BinCollection
    {
        foreach ($this->collections as $collection) {
            if ($collection->bin === $bin) {
                return $collection;
            }
        }

        return null;
    }

    /**
     * The collections whose `bin` is a known {@see BinType}, hydrated.
     *
     * @return list<BinCollection>
     */
    private static function knownCollections(mixed $collections): array
    {
        $known = [];

        foreach (is_array($collections) ? $collections : [] as $collection) {
            if (is_array($collection) && is_string($collection['bin'] ?? null) && BinType::tryFrom($collection['bin']) !== null) {
                $known[] = BinCollection::fromArray($collection);
            }
        }

        return $known;
    }
}
