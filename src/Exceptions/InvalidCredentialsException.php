<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Exceptions;

/**
 * Thrown on a 401 — bad email/password, or an invalid/expired token.
 */
final class InvalidCredentialsException extends SupportManagerApiException
{
    //
}
