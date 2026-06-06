<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Profile;

/**
 * A teammate as seen in the user directory (`GET users` / `GET users/{user}`).
 *
 * Mirrors the platform's `UserDirectoryResource` / `UserProfileResource`: name,
 * avatar and team role, plus the mobile number when the directory view exposes
 * it (own profile, or an admin/manager viewing a teammate).
 */
final readonly class DirectoryUser
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $avatar,
        public ?string $role,
        public ?string $mobileNumber,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            avatar: isset($data['avatar']) ? (string) $data['avatar'] : null,
            role: isset($data['role']) ? (string) $data['role'] : null,
            mobileNumber: isset($data['mobile_number']) ? (string) $data['mobile_number'] : null,
        );
    }
}
