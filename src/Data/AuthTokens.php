<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data;

use SupportManager\Sdk\Data\Hydration\Attributes\Defaults;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * An issued access/refresh token pair from the SupportManager API.
 */
final readonly class AuthTokens
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        #[Defaults('Bearer')]
        public string $tokenType,
        public int $expiresIn,
        public ?AuthenticatedUser $user = null,
        public ?string $deviceToken = null,
    ) {}

    /**
     * Build from the API's "data" payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?string $deviceToken = null): self
    {
        return Hydrator::hydrate(self::class, $data, ['deviceToken' => $deviceToken]);
    }
}
