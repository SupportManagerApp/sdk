<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Profile;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * One UK address autocomplete match from `GET user/address/suggestions`.
 *
 * `address` is the one-line label to show in a picker; pass `id` to
 * {@see \SupportManager\Sdk\Resources\Profile\ProfileResource::addressSuggestion()}
 * to get the fields ready for `saveAddress()`.
 */
final readonly class AddressSuggestion
{
    public function __construct(
        public string $id,
        public string $address,
    ) {}

    /**
     * @param  array<string, mixed>  $data  An entry from the response `data`.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
