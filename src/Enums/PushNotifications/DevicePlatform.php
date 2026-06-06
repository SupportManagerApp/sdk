<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\PushNotifications;

/**
 * The platform a device token belongs to, mirroring the platform's
 * `DevicePlatform` enum. The values are what the API stores and expects.
 */
enum DevicePlatform: string
{
    case Ios = 'ios';
    case Android = 'android';
    case Web = 'web';
}
