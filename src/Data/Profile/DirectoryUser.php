<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Profile;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A teammate as seen in the user directory (`GET users` / `GET users/{user}`).
 *
 * Mirrors the platform's `UserDirectoryResource` / `UserProfileResource`: name,
 * avatar and team role, plus the mobile number on the single-user view (the
 * list never carries it, so it is null there).
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
        return Hydrator::hydrate(self::class, $data);
    }
}
