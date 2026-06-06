<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Profile;

/**
 * The user's UK postal address.
 *
 * Mirrors the platform's `AddressResource`. Every line is encrypted at rest on
 * the server; the SDK simply receives the decrypted values.
 */
final readonly class Address
{
    public function __construct(
        public string $id,
        public string $addressLineOne,
        public ?string $addressLineTwo,
        public ?string $addressLineThree,
        public string $town,
        public ?string $county,
        public string $postcode,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            addressLineOne: (string) ($data['address_line_one'] ?? ''),
            addressLineTwo: isset($data['address_line_two']) ? (string) $data['address_line_two'] : null,
            addressLineThree: isset($data['address_line_three']) ? (string) $data['address_line_three'] : null,
            town: (string) ($data['town'] ?? ''),
            county: isset($data['county']) ? (string) $data['county'] : null,
            postcode: (string) ($data['postcode'] ?? ''),
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}
