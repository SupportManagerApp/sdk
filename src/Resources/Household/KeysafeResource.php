<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Household;

use SupportManager\Sdk\Data\Household\Keysafe;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * The team's keysafe — a singleton, not a collection.
 *
 * `show()` and `update()` report whether a code is set and whether the caller
 * may edit it; `reveal()` returns the actual code (and is the only method that
 * populates `Keysafe::$code`). The platform's `KeysafeController` returns a flat
 * `{data: {...}}` body — NOT a JSON:API document with `attributes` — so it is
 * read with {@see Resource::data()} rather than `item()`.
 *
 * ```php
 * SupportManager::keysafe()->reveal()->code;
 * SupportManager::keysafe()->update('1234');
 * ```
 *
 * @extends Resource<Keysafe>
 */
final class KeysafeResource extends Resource
{
    /**
     * The keysafe state: whether a code exists and whether it can be edited.
     */
    public function show(): ?Keysafe
    {
        return $this->data($this->get($this->path()), Keysafe::class);
    }

    /**
     * Reveal the keysafe code (audited server-side). `code` is null when unset.
     */
    public function reveal(): ?Keysafe
    {
        return $this->data($this->post("{$this->path()}/reveal"), Keysafe::class);
    }

    /**
     * Set or clear the keysafe code, returning the updated state. Pass null (or
     * an empty string) to clear it.
     */
    public function update(?string $code): ?Keysafe
    {
        return $this->data($this->patch($this->path(), ['code' => $code]), Keysafe::class);
    }

    protected function path(): string
    {
        return 'household/keysafe';
    }

    protected function hydrate(ResourceObject $object): Keysafe
    {
        return Keysafe::fromArray($object->flat());
    }
}
