<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Hydration\Casts;

use SupportManager\Sdk\Data\Hydration\Caster;
use SupportManager\Sdk\Enums\Dashboard\WeatherCondition;

/**
 * A {@see WeatherCondition} that reads a missing or unknown value as
 * `overcast`, as the platform's spec tells clients to.
 */
final readonly class WeatherConditionOrOvercast implements Caster
{
    public function cast(mixed $value, array $data): WeatherCondition
    {
        if ($value instanceof WeatherCondition) {
            return $value;
        }

        return (is_string($value) ? WeatherCondition::tryFrom($value) : null) ?? WeatherCondition::Overcast;
    }
}
