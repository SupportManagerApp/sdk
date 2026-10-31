<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake the caller's calendar feed address — a flat (`type()` is null)
 * singleton `{data: {url}}`.
 *
 * The seeded record's `url` is null until a feed is issued, as on the
 * platform; {@see issued()} seeds one already issued. `regenerateFeed()` mints
 * a new deterministic address each call, replacing the old one.
 *
 * ```php
 * SupportManager::fake()->seed(CalendarFeedFactory::new());
 * SupportManager::rotaCalendar()->feed()->url;           // null
 * SupportManager::rotaCalendar()->regenerateFeed()->url; // https://supportmanager.test/calendar/….ics
 * ```
 */
final class CalendarFeedFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'rota/calendar-feed';
    }

    public function definition(int $index): array
    {
        return ['url' => null];
    }

    /**
     * A feed whose address has already been issued.
     */
    public function issued(): static
    {
        return $this->state(['url' => self::url(0)]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::flat('rota/calendar-feed', static fn (RecordStore $store): array => [
                'url' => $store->all('rota/calendar-feed')[0]['url'] ?? null,
            ], teamMeta: true),

            FakeRoute::flat('rota/calendar-feed/token', static function (RecordStore $store): array {
                $url = self::url((int) $store->nextId('rota/calendar-feed'));
                $store->put('rota/calendar-feed', [['url' => $url]]);

                return ['url' => $url];
            }, teamMeta: true, method: 'POST'),
        ];
    }

    /**
     * A deterministic feed address for the nth issued token.
     */
    private static function url(int $number): string
    {
        return sprintf('https://supportmanager.test/calendar/00000000-0000-4000-8000-%012d.ics', $number);
    }
}
