<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Profile;

use SupportManager\Sdk\Data\Dashboard\Capabilities;
use SupportManager\Sdk\Data\Hydration\Hydrator;
use SupportManager\Sdk\Data\Leave\CurrentLeaveYear;

/**
 * The authenticated user's identity card, as returned by `GET user`.
 *
 * Mirrors the platform's `UserJsonResource`: the basics plus the avatar URL and
 * the user's role within the current team (null when not team-scoped).
 *
 * It also carries the session facts a client needs once per session:
 * `capabilities`, the same {@see Capabilities} as `GET capabilities`, and the
 * team's {@see CurrentLeaveYear}. Both are null when the platform leaves them
 * out. Reading the user through `profile()->me()` also remembers its
 * capabilities for the session, so a later `dashboard()->capabilities()`
 * needs no request.
 */
final readonly class User
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public ?string $avatar,
        public ?string $role,
        public ?Capabilities $capabilities = null,
        public ?CurrentLeaveYear $currentLeaveYear = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
