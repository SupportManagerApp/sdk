<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Dashboard;

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
        public array $items,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data  An area entry from `data.navigation`.
     */
    public static function fromArray(array $data): self
    {
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];

        return new self(
            key: (string) ($data['key'] ?? ''),
            label: (string) ($data['label'] ?? ''),
            icon: isset($data['icon']) ? (string) $data['icon'] : null,
            items: array_values(array_map(
                static fn (mixed $item): NavigationItem => NavigationItem::fromArray(is_array($item) ? $item : []),
                $items,
            )),
        );
    }
}
