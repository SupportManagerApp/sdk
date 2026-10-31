<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Dashboard;

use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;
use SupportManager\Sdk\Data\Hydration\Hydrator;

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
        #[ListOf(DashboardWidget::class)]
        public array $widgets,
        #[ListOf(DashboardQuickLink::class)]
        public array $quickLinks,
        #[ListOf(DashboardAlert::class)]
        public array $alerts,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The response `data` member.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
