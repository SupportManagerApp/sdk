<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Profile;

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
        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            relationship: (string) ($data['relationship'] ?? ''),
            phoneNumber: (string) ($data['phone_number'] ?? ''),
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}
