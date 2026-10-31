<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Dashboard;

/**
 * A page in a {@see NavigationArea}. `routeName` is the page's web route name
 * (e.g. `payroll.index`), for mapping onto a native screen, or null when the
 * platform could not resolve one.
 */
final readonly class NavigationItem
{
    public function __construct(
        public string $label,
        public ?string $icon,
        public ?string $routeName,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data  An item entry from an area's `items`.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            label: (string) ($data['label'] ?? ''),
            icon: isset($data['icon']) ? (string) $data['icon'] : null,
            routeName: isset($data['route_name']) ? (string) $data['route_name'] : null,
        );
    }
}
