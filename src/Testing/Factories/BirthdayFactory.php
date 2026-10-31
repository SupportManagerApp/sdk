<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake the read-only birthdays calendar feed.
 *
 * A flat (`type()` is null) list feed of `{date, user_id, user_name}` rows,
 * returned verbatim under `data`. An explicit GET returns every seeded row.
 *
 * ```php
 * SupportManager::fake()->seed(BirthdayFactory::new()->count(2));
 * SupportManager::rotaCalendar()->birthdays();
 * ```
 */
final class BirthdayFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'rota/birthdays';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'date' => '2026-06-10',
            'user_id' => 6 + $number,
            'user_name' => "Member {$number}",
        ];
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::collection('rota/birthdays', static fn (RecordStore $store): array => $store->all('rota/birthdays')),
        ];
    }
}
