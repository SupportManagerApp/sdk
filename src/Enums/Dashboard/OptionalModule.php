<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Dashboard;

/**
 * An optional module a team can switch on, mirroring the platform's
 * `OptionalModule`. The values are the keys of
 * {@see \SupportManager\Sdk\Data\Dashboard\Capabilities::$modules}; the platform
 * may add more, so that map keeps plain string keys.
 */
enum OptionalModule: string
{
    case Household = 'household';
    case CarJourneys = 'car_journeys';
    case Accidents = 'accidents';
    case Recruitment = 'recruitment';
    case Agencies = 'agencies';
    case ESigning = 'e_signing';
}
