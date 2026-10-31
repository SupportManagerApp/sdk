<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Profile;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * The authenticated user's identity card, as returned by `GET user`.
 *
 * Mirrors the platform's `UserJsonResource`: the basics plus the avatar URL and
 * the user's role within the current team (null when not team-scoped).
 */
final readonly class User
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public ?string $avatar,
        public ?string $role,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
