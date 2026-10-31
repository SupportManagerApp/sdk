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
 * may edit it; `reveal()` returns the code itself, as a string. The platform's
 * `KeysafeController` returns a flat
 * `{data: {...}}` body — NOT a JSON:API document with `attributes` — so it is
 * read with {@see Resource::data()} rather than `item()`.
 *
 * ```php
 * SupportManager::keysafe()->reveal();    // '1234', or null when no code is set
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
     * Reveal the keysafe code, or null when none is set. The platform audits
     * every reveal of a set code. Its body is only `{data: {code}}`, with no
     * `has_code` or `can_edit`, so this returns the code rather than a
     * {@see Keysafe}; call {@see self::show()} for those.
     */
    public function reveal(): ?string
    {
        $code = $this->raw($this->post("{$this->path()}/reveal"))['code'] ?? null;

        return is_string($code) && $code !== '' ? $code : null;
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
