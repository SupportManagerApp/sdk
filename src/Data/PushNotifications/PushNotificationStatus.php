<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\PushNotifications;

/**
 * The authenticated user's push-notification posture: whether they have any
 * registered tokens, how many, and whether they have dismissed the in-app
 * setup prompt.
 *
 * Mirrors the platform's `GET push-notifications/tokens/status` payload.
 */
final readonly class PushNotificationStatus
{
    public function __construct(
        public bool $hasTokens,
        public int $tokenCount,
        public bool $promptDismissed,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The response `data` member.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            hasTokens: (bool) ($data['has_tokens'] ?? false),
            tokenCount: (int) ($data['token_count'] ?? 0),
            promptDismissed: (bool) ($data['prompt_dismissed'] ?? false),
        );
    }
}
