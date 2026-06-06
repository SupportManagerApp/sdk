<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data;

/**
 * An issued access/refresh token pair from the SupportManager API.
 */
final readonly class AuthTokens
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
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
        return new self(
            accessToken: (string) $data['access_token'],
            refreshToken: (string) $data['refresh_token'],
            tokenType: (string) ($data['token_type'] ?? 'Bearer'),
            expiresIn: (int) ($data['expires_in'] ?? 0),
            user: isset($data['user']) ? AuthenticatedUser::fromArray($data['user']) : null,
            deviceToken: $deviceToken,
        );
    }
}
