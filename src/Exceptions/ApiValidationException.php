<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Exceptions;

/**
 * Thrown on a 422 — carries the per-field validation errors returned by the
 * API so they can be surfaced on the relevant form fields.
 */
final class ApiValidationException extends SupportManagerApiException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        string $message,
        public readonly array $errors = [],
    ) {
        parent::__construct($message, 422);
    }

    /**
     * The first error message for a given field, if any.
     */
    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }
}
