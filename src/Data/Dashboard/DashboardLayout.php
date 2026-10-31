<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Dashboard;

/**
 * How the current user's dashboard is put together: which widgets they see and
 * in what order, the quick links beneath them, and the alerts waiting for
 * their attention.
 *
 * Mirrors the platform's `GET dashboard` payload. It carries no widget content;
 * each widget's own module endpoint serves that (see {@see DashboardWidget}).
 */
final readonly class DashboardLayout
{
    /**
     * @param  list<DashboardWidget>  $widgets  Sorted by priority, lowest first.
     * @param  list<DashboardQuickLink>  $quickLinks
     * @param  list<DashboardAlert>  $alerts
     */
    public function __construct(
        public array $widgets,
        public array $quickLinks,
        public array $alerts,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The response `data` member.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            widgets: self::many($data['widgets'] ?? null, DashboardWidget::fromArray(...)),
            quickLinks: self::many($data['quick_links'] ?? null, DashboardQuickLink::fromArray(...)),
            alerts: self::many($data['alerts'] ?? null, DashboardAlert::fromArray(...)),
        );
    }

    /**
     * @template TItem
     *
     * @param  callable(array<array-key, mixed>): TItem  $factory
     * @return list<TItem>
     */
    private static function many(mixed $rows, callable $factory): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $row): mixed => $factory(is_array($row) ? $row : []),
            $rows,
        ));
    }
}
