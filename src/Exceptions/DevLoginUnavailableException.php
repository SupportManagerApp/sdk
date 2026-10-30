<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Exceptions;

/**
 * Thrown when a developer login endpoint answers 404: the API is a production
 * (or otherwise non-enabled) environment, where those routes are not
 * registered at all. Treat it as "no developer login here" — hide the picker —
 * rather than as an error worth surfacing.
 */
final class DevLoginUnavailableException extends SupportManagerApiException
{
    public function __construct(string $message = 'Developer login is not available on this environment.', ?string $body = null)
    {
        parent::__construct($message, 404, body: $body);
    }
}
