<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Teams;

/**
 * A team the authenticated user belongs to, mirroring the platform's
 * `TeamJsonResource`.
 *
 * `role` is the user's role within this team. The `id` is the value the SDK
 * sends as `X-Team-ID` to scope tenant requests — see
 * {@see \SupportManager\Sdk\Session\CurrentTeam}.
 */
final readonly class Team
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $role,
        public ?string $slug,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            role: isset($data['role']) ? (string) $data['role'] : null,
            slug: isset($data['slug']) ? (string) $data['slug'] : null,
        );
    }
}
