<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake dashboard weather forecast — a flat singleton at `dashboard/weather`
 * (`{data: {location, current, days}, meta}`), so `type()` stays null.
 *
 * Defaults to a partly cloudy evening in Weymouth with two days of forecast.
 * {@see self::unavailable()} answers `{data: null}`, as the platform does
 * while it cannot reach Open-Meteo.
 *
 * ```php
 * SupportManager::fake()->seed(WeatherForecastFactory::new());
 * SupportManager::dashboard()->weather()->current->temperature;   // 14
 *
 * SupportManager::fake()->seed(WeatherForecastFactory::new()->unavailable());
 * SupportManager::dashboard()->weather();                         // null
 * ```
 */
final class WeatherForecastFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'dashboard/weather';
    }

    public function definition(int $index): array
    {
        return [
            'location' => 'Weymouth',
            'current' => [
                'temperature' => 14,
                'feels_like' => 11,
                'condition' => 'partly_cloudy',
                'condition_label' => 'Partly cloudy',
                'icon' => 'cloud-moon',
                'is_daytime' => false,
                'wind_speed_mph' => 12,
            ],
            'days' => [
                ['date' => '2026-10-09', 'day_name' => 'Today', 'condition' => 'drizzle', 'condition_label' => 'Drizzle', 'icon' => 'cloud-drizzle', 'high' => 18, 'low' => 14, 'rain_chance' => 60],
                ['date' => '2026-10-10', 'day_name' => 'Sat', 'condition' => 'thunderstorm', 'condition_label' => 'Thunderstorms', 'icon' => 'cloud-lightning', 'high' => 13, 'low' => 8, 'rain_chance' => 90],
            ],
        ];
    }

    /**
     * No forecast: the platform answers `{data: null}` while Open-Meteo is
     * unavailable.
     */
    public function unavailable(): static
    {
        return $this->state(['_unavailable' => true]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::flat('dashboard/weather', static function (RecordStore $store): ?array {
                $forecast = $store->all('dashboard/weather')[0] ?? null;

                if ($forecast === null || ($forecast['_unavailable'] ?? false) === true) {
                    return null;
                }

                unset($forecast['_unavailable']);

                return $forecast;
            }, teamMeta: true),
        ];
    }
}
