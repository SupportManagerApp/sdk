<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Security;

/**
 * A social sign-in provider, mirroring the platform's `SocialProvider` enum.
 * The values are the `{provider}` path segment of `auth/social/{provider}` and
 * `user/social-accounts/{provider}`.
 */
enum SocialProvider: string
{
    case Google = 'google';
    case Microsoft = 'microsoft';
    case Apple = 'apple';
}
