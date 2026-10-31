<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A team membership on a {@see DevLoginUser}, with the role and status the
 * account holds in that team.
 *
 * `role` (e.g. Admin, Manager, PA, Family Member, Contractor) and `status` are
 * open-ended on the platform, so they are kept as strings; either is null when
 * the account has no settings row for the team. The `id` is the value to send
 * as `X-Team-ID` once logged in.
 */
final readonly class DevLoginTeam
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public ?string $role = null,
        public ?string $status = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
