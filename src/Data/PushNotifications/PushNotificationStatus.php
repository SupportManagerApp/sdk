<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\PushNotifications;

use SupportManager\Sdk\Data\Hydration\Hydrator;

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
        return Hydrator::hydrate(self::class, $data);
    }
}
