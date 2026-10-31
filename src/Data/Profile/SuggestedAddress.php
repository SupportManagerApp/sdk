<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Profile;

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
        public ?string $addressLineTwo,
        public ?string $addressLineThree,
        public string $town,
        public ?string $county,
        public string $postcode,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The response `data` member.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            addressLineOne: (string) ($data['address_line_one'] ?? ''),
            addressLineTwo: self::optional($data['address_line_two'] ?? null),
            addressLineThree: self::optional($data['address_line_three'] ?? null),
            town: (string) ($data['town'] ?? ''),
            county: self::optional($data['county'] ?? null),
            postcode: (string) ($data['postcode'] ?? ''),
        );
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

    /**
     * The lookup sends unused lines as empty strings; treat those as absent.
     */
    private static function optional(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
