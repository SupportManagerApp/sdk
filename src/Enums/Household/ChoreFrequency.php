<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Household;

/**
 * How often a chore recurs. Mirrors the platform's `ChoreFrequency` enum; the
 * backing values are the human-readable labels the API stores and returns.
 */
enum ChoreFrequency: string
{
    case Daily = 'Daily';
    case ThreeDays = 'Three Days';
    case Weekly = 'Weekly';
    case Fortnightly = 'Fortnightly';
    case Monthly = 'Monthly';
    case Custom = 'Custom';
}
