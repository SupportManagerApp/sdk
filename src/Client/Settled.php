<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Client;

use Throwable;

/**
 * The outcome of one call made through
 * {@see \SupportManager\Sdk\SupportManager::settle()}: its value, or the
 * exception it would have thrown had it run on its own (the same typed
 * exception, such as a `SupportManagerApiException` or
 * `SessionExpiredException`).
 *
 * ```php
 * ['profile' => $profile, 'leave' => $leave] = SupportManager::settle(
 *     profile: fn ($sm) => $sm->profile()->show(),
 *     leave: fn ($sm) => $sm->leaveBalance()->show(),
 * );
 *
 * if ($leave->failed()) { … $leave->exception() … }
 * $profile->value(); // throws the slot's exception when it failed
 * ```
 */
final readonly class Settled
{
    private function __construct(
        private bool $ok,
        private mixed $result,
        private ?Throwable $error,
    ) {}

    public static function fromValue(mixed $value): self
    {
        return new self(true, $value, null);
    }

    public static function fromException(Throwable $exception): self
    {
        return new self(false, null, $exception);
    }

    public function ok(): bool
    {
        return $this->ok;
    }

    public function failed(): bool
    {
        return ! $this->ok;
    }

    /**
     * The call's return value. A failed call throws its exception here.
     *
     * @throws Throwable
     */
    public function value(): mixed
    {
        if ($this->error !== null) {
            throw $this->error;
        }

        return $this->result;
    }

    /**
     * The exception the call threw, or null when it succeeded.
     */
    public function exception(): ?Throwable
    {
        return $this->error;
    }
}
