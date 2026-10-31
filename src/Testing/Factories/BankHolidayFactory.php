<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake the read-only bank-holidays calendar feed.
 *
 * A flat (`type()` is null) list feed: rows are returned verbatim under `data`,
 * not as JSON:API resource objects. The generic flat handler is a singleton, so
 * this declares an explicit GET that returns every seeded row as a flat
 * collection.
 *
 * ```php
 * SupportManager::fake()->seed(BankHolidayFactory::new()->count(2));
 * SupportManager::rotaCalendar()->bankHolidays([
 *     'filter' => ['start_date' => '2026-01-01', 'end_date' => '2026-12-31'],
 * ]);
 * ```
 */
final class BankHolidayFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'rota/bank-holidays';
    }

    public function definition(int $index): array
    {
        $day = mb_str_pad((string) (25 + $index), 2, '0', STR_PAD_LEFT);

        return [
            'date' => "2026-12-{$day}",
            'name' => $index === 0 ? 'Christmas Day' : 'Boxing Day',
            'region' => 'england-and-wales',
        ];
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::collection('rota/bank-holidays', static fn (RecordStore $store): array => $store->all('rota/bank-holidays')),
        ];
    }
}
