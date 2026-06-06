<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Exceptions;

use RuntimeException;

/**
 * Thrown when an authenticated request cannot proceed because the session has
 * ended and could not be refreshed (no stored session, or the refresh token
 * was rejected). The session has already been cleared by the time this is
 * thrown; the UI layer should catch it and full-redirect to the launch gate.
 */
final class SessionExpiredException extends RuntimeException
{
    public static function unrecoverable(): self
    {
        return new self('Your session has expired. Please sign in again.');
    }
}
