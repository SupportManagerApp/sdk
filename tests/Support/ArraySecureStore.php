<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Support;

use SupportManager\Sdk\Contracts\SecureStore;

/**
 * In-memory {@see SecureStore} for tests, standing in for the device keychain.
 */
final class ArraySecureStore implements SecureStore
{
    /** @var array<string, string> */
    public array $items = [];

    public function put(string $key, ?string $value): void
    {
        if ($value === null) {
            unset($this->items[$key]);

            return;
        }

        $this->items[$key] = $value;
    }

    public function get(string $key): ?string
    {
        return $this->items[$key] ?? null;
    }

    public function forget(string $key): void
    {
        unset($this->items[$key]);
    }
}
