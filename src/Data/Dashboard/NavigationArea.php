<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Dashboard;

use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * One area of the web navigation (e.g. `pay`) with the pages in it that are
 * about the user themselves. Team-wide pages are left out, and areas with no
 * such pages are not sent at all.
 */
final readonly class NavigationArea
{
    /**
     * @param  list<NavigationItem>  $items
     */
    public function __construct(
        public string $key,
        public string $label,
        public ?string $icon,
        #[ListOf(NavigationItem::class)]
        public array $items,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data  An area entry from `data.navigation`.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
