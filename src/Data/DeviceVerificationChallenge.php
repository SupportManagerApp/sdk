<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data;

/**
 * The challenge returned (HTTP 403) when login succeeds but the device is
 * not yet trusted. The verification token authorises the follow-up
 * device-verification requests.
 */
final readonly class DeviceVerificationChallenge
{
    /**
     * @param  list<DeviceVerificationMethod>  $methods
     */
    public function __construct(
        public string $verificationToken,
        public int $expiresIn,
        public array $methods,
        public ?int $userId = null,
        public ?string $userName = null,
    ) {}

    /**
     * Build from the API's "data" payload of a 403 response.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            verificationToken: (string) $data['verification_token'],
            expiresIn: (int) ($data['verification_token_expires_in'] ?? 0),
            methods: array_map(
                static fn (array $method): DeviceVerificationMethod => DeviceVerificationMethod::fromArray($method),
                $data['available_methods'] ?? [],
            ),
            userId: isset($data['user']['id']) ? (int) $data['user']['id'] : null,
            userName: $data['user']['name'] ?? null,
        );
    }
}
