<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Dashboard;

use SupportManager\Sdk\Data\Hydration\Attributes\CastWith;
use SupportManager\Sdk\Data\Hydration\Casts\WeatherConditionOrOvercast;
use SupportManager\Sdk\Data\Hydration\Hydrator;
use SupportManager\Sdk\Enums\Dashboard\WeatherCondition;

/**
 * One day of the forecast, from the platform's `GET dashboard/weather`
 * (`data.days`). Temperatures are whole degrees Celsius.
 *
 * `date` is the `Y-m-d` day in Weymouth (Europe/London), and `dayName` is
 * `Today` or the short English weekday (`Sat`). `icon` is the Lucide icon name
 * for the daytime condition, and `rainChance` the day's highest chance of
 * rain, as a percentage. An unknown `condition` reads as
 * {@see WeatherCondition::Overcast}.
 */
final readonly class DailyWeather
{
    public function __construct(
        public string $date,
        public string $dayName,
        #[CastWith(WeatherConditionOrOvercast::class)]
        public WeatherCondition $condition,
        public string $conditionLabel,
        public string $icon,
        public int $high,
        public int $low,
        public int $rainChance,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data  An entry from the forecast's `days`.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
