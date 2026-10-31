<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake trusted devices (JSON:API type `trusted_devices`, at
 * `user/trusted-devices`), as TrustedDeviceController answers them: the index
 * is unpaginated and most recently used first, a revoke answers 204 (404 for
 * a device that isn't stored), and revoking the others keeps every device
 * seeded {@see self::current()} and answers `{data: {revoked_count}}`.
 *
 * ```php
 * SupportManager::fake()->seed(
 *     TrustedDeviceFactory::new()->current(),
 *     TrustedDeviceFactory::new()->count(2),
 * );
 * SupportManager::trustedDevices()->destroyOthers();   // 2
 * ```
 */
final class TrustedDeviceFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'user/trusted-devices';
    }

    public function type(): string
    {
        return 'trusted_devices';
    }

    public function definition(int $index): array
    {
        $day = mb_str_pad((string) max(1, 30 - $index), 2, '0', STR_PAD_LEFT);

        return [
            'device_name' => 'Chrome on Windows',
            'device_type' => 'Desktop',
            'masked_ip_address' => '81.2.x.x',
            'is_current_device' => false,
            'verified_at' => '2026-10-01T09:00:00+00:00',
            'last_used_at' => "2026-10-{$day}T09:00:00+00:00",
            'expires_at' => '2026-11-30T09:00:00+00:00',
        ];
    }

    /**
     * The device making the requests: flagged `is_current_device`, and kept by
     * `destroyOthers()`.
     */
    public function current(): static
    {
        return $this->state([
            'device_name' => 'Safari on iPhone',
            'device_type' => 'Mobile',
            'is_current_device' => true,
            'last_used_at' => '2026-10-31T09:00:00+00:00',
        ]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::collection('user/trusted-devices', static function (RecordStore $store): array {
                $devices = $store->all('user/trusted-devices');
                usort($devices, static fn (array $a, array $b): int => strcmp((string) ($b['last_used_at'] ?? ''), (string) ($a['last_used_at'] ?? '')));

                return $devices;
            }),

            FakeRoute::delete('user/trusted-devices/{device}', static fn (RecordStore $store, array $matches): mixed => $store->delete('user/trusted-devices', $matches[1])
                ? Http::response(null, 204)
                : Json::notFound()),

            FakeRoute::delete('user/trusted-devices', static function (RecordStore $store): mixed {
                $devices = $store->all('user/trusted-devices');
                $kept = array_values(array_filter($devices, static fn (array $device): bool => ($device['is_current_device'] ?? false) === true));
                $store->put('user/trusted-devices', $kept);

                return Http::response(Json::flat(['revoked_count' => count($devices) - count($kept)]));
            }),
        ];
    }
}
