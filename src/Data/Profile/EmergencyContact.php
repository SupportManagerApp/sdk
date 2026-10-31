<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Profile;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * One of the user's emergency contacts for the current team.
 *
 * Mirrors the platform's `EmergencyContactResource`. A user may have several;
 * pass `id` back to {@see \SupportManager\Sdk\Resources\Profile\ProfileResource::saveEmergencyContacts()}
 * to update a contact rather than add a new one. Name and phone number are
 * encrypted at rest on the server.
 */
final readonly class EmergencyContact
{
    public function __construct(
        public string $id,
        public string $name,
        public string $relationship,
        public string $phoneNumber,
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
