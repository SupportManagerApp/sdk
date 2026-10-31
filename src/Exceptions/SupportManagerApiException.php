<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base exception for any SupportManager API failure (network errors,
 * unexpected status codes, etc.).
 */
class SupportManagerApiException extends RuntimeException
{
    public function __construct(
        string $message = 'The SupportManager request failed.',
        public readonly int $status = 0,
        ?Throwable $previous = null,
        public readonly ?string $body = null,
    ) {
        parent::__construct($message, $status, $previous);
    }
}
