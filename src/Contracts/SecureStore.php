<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Contracts;

/**
 * A small key/value abstraction over secure storage. On mobile this is the iOS
 * Keychain / Android Keystore; on desktop the OS secret store; in tests an
 * in-memory fake. Each host app binds its own implementation. Kept deliberately
 * narrow so it can be swapped without touching the session layer.
 */
interface SecureStore
{
    /**
     * Persist a value. Passing null removes the key.
     */
    public function put(string $key, ?string $value): void;

    /**
     * Read a value, or null when the key is absent.
     */
    public function get(string $key): ?string;

    /**
     * Remove a key, if present.
     */
    public function forget(string $key): void;
}
