<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data;

/**
 * An account offered on the developer login picker, mirroring the platform's
 * `DevLoginUserResource`. Pass {@see self::$id} to
 * {@see \SupportManager\Sdk\Client\SupportManagerClient::devLogin()}.
 */
final readonly class DevLoginUser
{
    /**
     * @param  list<DevLoginTeam>  $teams  Every team the account belongs to; empty if none.
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public array $teams = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            name: (string) $data['name'],
            email: (string) $data['email'],
            teams: array_values(array_map(
                static fn (array $team): DevLoginTeam => DevLoginTeam::fromArray($team),
                array_filter(
                    is_array($data['teams'] ?? null) ? $data['teams'] : [],
                    is_array(...),
                ),
            )),
        );
    }
}
