<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Household;

/**
 * A bin the council collects. Mirrors the platform's `BinType`;
 * {@see self::label()} matches the `label` it sends.
 */
enum BinType: string
{
    case Rubbish = 'rubbish';
    case Recycling = 'recycling';
    case Food = 'food';

    /**
     * The platform's label for the bin, e.g. `Recycling`.
     */
    public function label(): string
    {
        return match ($this) {
            self::Rubbish => 'Rubbish',
            self::Recycling => 'Recycling',
            self::Food => 'Food',
        };
    }
}
