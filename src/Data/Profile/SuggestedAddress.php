<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Profile;

use SupportManager\Sdk\Data\Hydration\Attributes\CastWith;
use SupportManager\Sdk\Data\Hydration\Casts\EmptyStringAsNull;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * The address fields for a chosen suggestion, from
 * `GET user/address/suggestions/{id}`.
 *
 * The platform returns these as a flat `{data: {...}}` body (not a JSON:API
 * document) in exactly the shape `PUT user/address` accepts, with lookup lines
 * 3 and 4 joined into `addressLineThree`. {@see self::toArray()} gives that
 * payload back for {@see \SupportManager\Sdk\Resources\Profile\ProfileResource::saveAddress()}.
 */
final readonly class SuggestedAddress
{
    public function __construct(
        public string $addressLineOne,
        #[CastWith(EmptyStringAsNull::class)]
        public ?string $addressLineTwo,
        #[CastWith(EmptyStringAsNull::class)]
        public ?string $addressLineThree,
        public string $town,
        #[CastWith(EmptyStringAsNull::class)]
        public ?string $county,
        public string $postcode,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The response `data` member.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }

    /**
     * The `PUT user/address` payload for this address.
     *
     * @return array{address_line_one: string, address_line_two: string|null, address_line_three: string|null, town: string, county: string|null, postcode: string}
     */
    public function toArray(): array
    {
        return [
            'address_line_one' => $this->addressLineOne,
            'address_line_two' => $this->addressLineTwo,
            'address_line_three' => $this->addressLineThree,
            'town' => $this->town,
            'county' => $this->county,
            'postcode' => $this->postcode,
        ];
    }
}
