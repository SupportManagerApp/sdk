<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Dashboard;

use SupportManager\Sdk\Data\Hydration\Attributes\Defaults;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A widget's placement on the dashboard — no content, just where it goes.
 *
 * `key` names the widget as `{module}.{widget}` (e.g. `rota.today-rota`); the
 * client fetches its content from that module's resource and should skip keys
 * it does not recognise. `width` is `full` or `half` (treat anything else as
 * `full`); lower `priority` sorts first.
 */
final readonly class DashboardWidget
{
    public function __construct(
        public string $key,
        #[Defaults('full')]
        public string $width,
        public int $priority,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data  A widget entry from `data.widgets`.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }

    /**
     * Whether the widget renders at half width.
     */
    public function isHalfWidth(): bool
    {
        return $this->width === 'half';
    }
}
