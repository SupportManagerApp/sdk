<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Dashboard;

/**
 * A shortcut shown beneath the dashboard widgets. `url` is absolute and already
 * resolved for the current team.
 */
final readonly class DashboardQuickLink
{
    public function __construct(
        public string $label,
        public ?string $description,
        public ?string $icon,
        public ?string $color,
        public string $url,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data  A quick link entry from `data.quick_links`.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            label: (string) ($data['label'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
            icon: isset($data['icon']) ? (string) $data['icon'] : null,
            color: isset($data['color']) ? (string) $data['color'] : null,
            url: (string) ($data['url'] ?? ''),
        );
    }
}
