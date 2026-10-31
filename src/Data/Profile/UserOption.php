<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Profile;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A colleague as shown in a picker, from `GET users/options`.
 *
 * Mirrors the platform's `UserOptionResource`: just enough to render a choice
 * (id, name and avatar), for example an accident report's injured person or an
 * appointment's assignee.
 */
final readonly class UserOption
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $avatar,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
