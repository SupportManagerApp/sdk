<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Enums\Household\BinType;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake household bin day — a flat singleton at `household/bin-day`
 * (`{data: {bins_due_tomorrow, collections}, meta}`), so `type()` stays null.
 *
 * The record holds each bin's next collection under its {@see BinType} value;
 * the route answers them in the platform's order (rubbish, recycling, food)
 * and derives `bins_due_tomorrow`. The dates are fixed: a bin due tomorrow is
 * collected on {@see self::TOMORROW}, any other on {@see self::NEXT_WEEK}.
 * By default rubbish and food go out tomorrow, as in the platform's example.
 *
 * ```php
 * SupportManager::fake()->seed(BinCollectionScheduleFactory::new()->dueTomorrow(BinType::Recycling));
 * SupportManager::binDay()->show()->binsDueTomorrow;   // [BinType::Recycling]
 * ```
 */
final class BinCollectionScheduleFactory extends Factory implements ProvidesFakeRoutes
{
    public const string TOMORROW = '2026-11-03';

    public const string NEXT_WEEK = '2026-11-10';

    public function path(): string
    {
        return 'household/bin-day';
    }

    public function definition(int $index): array
    {
        return self::collections(BinType::Rubbish, BinType::Food);
    }

    /**
     * Exactly these bins go out tomorrow; every other one next week. Pass
     * none for a week with nothing due. Apply {@see self::withoutDate()}
     * after it, as this resets every bin.
     */
    public function dueTomorrow(BinType ...$bins): static
    {
        return $this->state(self::collections(...$bins));
    }

    /**
     * The council has no date for these bins: no next collection, so not due.
     */
    public function withoutDate(BinType ...$bins): static
    {
        $state = [];

        foreach ($bins as $bin) {
            $state[$bin->value] = ['next_collection_on' => null, 'is_due_tomorrow' => false];
        }

        return $this->state($state);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::flat('household/bin-day', static function (RecordStore $store): array {
                $record = $store->all('household/bin-day')[0] ?? [];
                $collections = [];

                foreach (BinType::cases() as $bin) {
                    $collection = is_array($record[$bin->value] ?? null) ? $record[$bin->value] : [];

                    $collections[] = [
                        'bin' => $bin->value,
                        'label' => $bin->label(),
                        'next_collection_on' => $collection['next_collection_on'] ?? null,
                        'is_due_tomorrow' => ($collection['is_due_tomorrow'] ?? false) === true,
                    ];
                }

                return [
                    'bins_due_tomorrow' => array_values(array_map(
                        static fn (array $collection): string => $collection['bin'],
                        array_filter($collections, static fn (array $collection): bool => $collection['is_due_tomorrow']),
                    )),
                    'collections' => $collections,
                ];
            }, teamMeta: true),
        ];
    }

    /**
     * Every bin's next collection, with these due tomorrow.
     *
     * @return array<string, array{next_collection_on: string, is_due_tomorrow: bool}>
     */
    private static function collections(BinType ...$dueTomorrow): array
    {
        $collections = [];

        foreach (BinType::cases() as $bin) {
            $due = in_array($bin, $dueTomorrow, true);

            $collections[$bin->value] = [
                'next_collection_on' => $due ? self::TOMORROW : self::NEXT_WEEK,
                'is_due_tomorrow' => $due,
            ];
        }

        return $collections;
    }
}
