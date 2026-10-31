<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Dashboard;

/**
 * The weather, as the dashboard's weather widget groups Open-Meteo's WMO
 * codes. Mirrors the platform's `WeatherCondition`; {@see self::label()}
 * matches the `condition_label` it sends.
 *
 * The platform says to treat a value it adds later as `overcast`, so the
 * weather DTOs read an unknown value as {@see self::Overcast} rather than
 * failing.
 */
enum WeatherCondition: string
{
    case Clear = 'clear';
    case PartlyCloudy = 'partly_cloudy';
    case Overcast = 'overcast';
    case Fog = 'fog';
    case Drizzle = 'drizzle';
    case Rain = 'rain';
    case Showers = 'showers';
    case Snow = 'snow';
    case Thunderstorm = 'thunderstorm';

    /**
     * The platform's label for the condition, e.g. `Partly cloudy`.
     */
    public function label(): string
    {
        return match ($this) {
            self::Clear => 'Clear',
            self::PartlyCloudy => 'Partly cloudy',
            self::Overcast => 'Cloudy',
            self::Fog => 'Foggy',
            self::Drizzle => 'Drizzle',
            self::Rain => 'Rain',
            self::Showers => 'Showers',
            self::Snow => 'Snow',
            self::Thunderstorm => 'Thunderstorms',
        };
    }
}
