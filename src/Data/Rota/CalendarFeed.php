<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * The address of the caller's iCalendar feed of the team appointments they can
 * see, for subscribing in a calendar app.
 *
 * Mirrors the platform's flat `calendar-feed` `{data: {url}}` body. `url` is
 * null until one has been issued. Treat it as a secret: anybody with it can
 * read the feed.
 */
final readonly class CalendarFeed
{
    public function __construct(
        public ?string $url,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The flat `data` payload.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
