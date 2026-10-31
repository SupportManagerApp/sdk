<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Dashboard;

use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * The forecast behind the `dashboard.weather` widget, from the platform's
 * `GET dashboard/weather`: the current weather where the team is (Weymouth)
 * and the coming days, from Open-Meteo.
 *
 * `SupportManager::dashboard()->weather()` returns null instead while the
 * forecast is unavailable; the web widget hides itself then.
 */
final readonly class WeatherForecast
{
    /**
     * @param  list<DailyWeather>  $days  Today and the following days, up to five, in date order.
     */
    public function __construct(
        public string $location,
        public CurrentWeather $current,
        #[ListOf(DailyWeather::class)]
        public array $days,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The response `data` member.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
