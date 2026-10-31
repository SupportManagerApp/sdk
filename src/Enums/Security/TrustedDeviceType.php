<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Security;

/**
 * The kind of a trusted device, as the platform's `TrustedDevice::getDeviceType()`
 * guesses it from the User-Agent. The list is open, so a DTO reads an unknown
 * value as null.
 */
enum TrustedDeviceType: string
{
    case Desktop = 'Desktop';
    case Mobile = 'Mobile';
    case Tablet = 'Tablet';
}
