<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake the read-only work-anniversaries calendar feed.
 *
 * A flat (`type()` is null) list feed of `{date, user_id, user_name, years}`
 * rows, returned verbatim under `data`. An explicit GET returns every seeded
 * row.
 *
 * ```php
 * SupportManager::fake()->seed(AnniversaryFactory::new()->count(2));
 * SupportManager::rotaCalendar()->anniversaries('2026-06-01', '2026-06-30');
 * ```
 */
final class AnniversaryFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'rota/anniversaries';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'date' => '2026-06-12',
            'user_id' => 7 + $number,
            'user_name' => "Member {$number}",
            'years' => $number * 5,
        ];
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::collection('rota/anniversaries', static fn (RecordStore $store): array => $store->all('rota/anniversaries')),
        ];
    }
}
