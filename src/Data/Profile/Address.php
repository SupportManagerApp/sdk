<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Profile;

use SupportManager\Sdk\Data\Hydration\Hydrator;

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
        return Hydrator::hydrate(self::class, $data);
    }
}
