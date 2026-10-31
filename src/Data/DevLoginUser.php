<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data;

use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;
use SupportManager\Sdk\Data\Hydration\Hydrator;

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
        #[ListOf(DevLoginTeam::class)]
        public array $teams = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
