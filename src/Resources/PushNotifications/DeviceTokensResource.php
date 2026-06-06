<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\PushNotifications;

use SupportManager\Sdk\Data\PushNotifications\DeviceToken;
use SupportManager\Sdk\Data\PushNotifications\PushNotificationStatus;
use SupportManager\Sdk\Enums\PushNotifications\DevicePlatform;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Push-notification (FCM) device tokens for the authenticated user in the
 * current tenant.
 *
 * A client registers the device's token once it has one (`register()`),
 * inspects its posture (`status()`/`all()`), and tears tokens down on sign-out
 * or when permission is revoked (`forget()`/`destroy()`/`destroyAll()`).
 * `dismissPrompt()` records that the user has dismissed the in-app
 * enable-notifications prompt so it is not shown again.
 *
 * ```php
 * SupportManager::deviceTokens()->register($fcmToken, DevicePlatform::Ios);
 * SupportManager::deviceTokens()->status()->hasTokens;
 * SupportManager::deviceTokens()->forget($fcmToken);
 * ```
 *
 * @extends \SupportManager\Sdk\Resources\Resource<DeviceToken>
 */
final class DeviceTokensResource extends Resource
{
    /**
     * Register (or refresh) a device token for the current user and tenant,
     * returning the stored record. Re-registering an existing token moves it to
     * the current user/team and bumps its `last_used_at`.
     */
    public function register(string $token, DevicePlatform|string $platform): ?DeviceToken
    {
        return $this->item($this->post($this->path(), [
            'token' => $token,
            'platform' => $platform instanceof DevicePlatform ? $platform->value : $platform,
        ]));
    }

    /**
     * The current user's push-notification status (token presence/count and
     * whether the setup prompt has been dismissed).
     */
    public function status(): PushNotificationStatus
    {
        $response = $this->get("{$this->path()}/status");
        $this->throwForErrorResponse($response, $this->path());

        /** @var array<string, mixed> $data */
        $data = $response->json('data', []);

        return PushNotificationStatus::fromArray($data);
    }

    /**
     * Forget a token by its value — the usual client-side "deregister this
     * device" call. Succeeds whether or not the token existed.
     */
    public function forget(string $token): void
    {
        $this->throwForErrorResponse($this->delete($this->path(), ['token' => $token]), $this->path());
    }

    /**
     * Delete a single token record by its id (404s when it is not the user's).
     */
    public function destroy(string|int $id): void
    {
        $this->throwForErrorResponse($this->delete("{$this->path()}/{$id}"), $this->path());
    }

    /**
     * Delete every token for the current user in the current tenant.
     */
    public function destroyAll(): void
    {
        $this->throwForErrorResponse($this->delete("{$this->path()}/all"), $this->path());
    }

    /**
     * Dismiss the in-app enable-notifications prompt for the current user.
     */
    public function dismissPrompt(): void
    {
        $this->throwForErrorResponse($this->post('push-notifications/prompt/dismiss'), $this->path());
    }

    protected function path(): string
    {
        return 'push-notifications/tokens';
    }

    protected function hydrate(ResourceObject $object): DeviceToken
    {
        return DeviceToken::fromArray($object->flat());
    }
}
