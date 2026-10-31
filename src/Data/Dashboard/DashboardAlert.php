<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Dashboard;

/**
 * Something in the web app waiting for the user's attention, such as leave
 * requests to approve. `location` is where it sits in the web navigation and
 * `url` the absolute web page that deals with it.
 */
final readonly class DashboardAlert
{
    public function __construct(
        public string $description,
        public int $count,
        public ?string $location,
        public ?string $icon,
        public string $url,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data  An alert entry from `data.alerts`.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            description: (string) ($data['description'] ?? ''),
            count: (int) ($data['count'] ?? 0),
            location: isset($data['location']) ? (string) $data['location'] : null,
            icon: isset($data['icon']) ? (string) $data['icon'] : null,
            url: (string) ($data['url'] ?? ''),
        );
    }
}
