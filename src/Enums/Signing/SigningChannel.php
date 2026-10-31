<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Signing;

/**
 * Where a recipient signed, mirroring the platform's `SigningChannel`. Signing
 * through the API is always recorded as {@see self::App}.
 */
enum SigningChannel: string
{
    case Web = 'web';
    case App = 'app';
}
