<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Dashboard;

use SupportManager\Sdk\Data\Hydration\Attributes\CastWith;
use SupportManager\Sdk\Data\Hydration\Casts\WeatherConditionOrOvercast;
use SupportManager\Sdk\Data\Hydration\Hydrator;
use SupportManager\Sdk\Enums\Dashboard\WeatherCondition;

/**
 * The weather right now, from the platform's `GET dashboard/weather`
 * (`data.current`). Temperatures are whole degrees Celsius.
 *
 * `icon` is the Lucide icon name the web widget shows, already chosen for day
 * or night. An unknown `condition` reads as {@see WeatherCondition::Overcast}.
 */
final readonly class CurrentWeather
{
    public function __construct(
        public int $temperature,
        public int $feelsLike,
        #[CastWith(WeatherConditionOrOvercast::class)]
        public WeatherCondition $condition,
        public string $conditionLabel,
        public string $icon,
        public bool $isDaytime,
        public int $windSpeedMph,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data  The forecast's `current` member.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
