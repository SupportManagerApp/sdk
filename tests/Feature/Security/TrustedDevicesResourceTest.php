<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Security\TrustedDevice;
use SupportManager\Sdk\Enums\Security\TrustedDeviceType;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;

/*
 * Against src/Auth/Api/Http/Controllers/TrustedDeviceController.php and
 * TrustedDeviceResource on the platform.
 */

function trusted_device_object(int $id, bool $current, string $type = 'Mobile'): array
{
    return [
        'id' => (string) $id,
        'type' => 'trusted_devices',
        'attributes' => [
            'device_name' => 'Safari on iPhone',
            'device_type' => $type,
            'masked_ip_address' => '81.2.x.x',
            'is_current_device' => $current,
            'verified_at' => '2026-10-01T09:00:00+00:00',
            'last_used_at' => $current ? '2026-10-31T08:00:00+00:00' : null,
            'expires_at' => '2026-11-30T09:00:00+00:00',
        ],
    ];
}

it('lists the trusted devices, flagging this one, without a team header', function () {
    fakeApi(['user/trusted-devices' => ['data' => [
        trusted_device_object(12, current: true),
        trusted_device_object(13, current: false, type: 'Smart Fridge'),
    ]]]);

    $devices = sdk()->trustedDevices()->all();

    expect($devices)->toHaveCount(2)
        ->and($devices[0])->toBeInstanceOf(TrustedDevice::class)
        ->and($devices[0]->id)->toBe('12')
        ->and($devices[0]->deviceName)->toBe('Safari on iPhone')
        ->and($devices[0]->deviceType)->toBe(TrustedDeviceType::Mobile)
        ->and($devices[0]->maskedIpAddress)->toBe('81.2.x.x')
        ->and($devices[0]->isCurrentDevice)->toBeTrue()
        ->and($devices[0]->verifiedAt)->toBe('2026-10-01T09:00:00+00:00')
        ->and($devices[0]->lastUsedAt)->toBe('2026-10-31T08:00:00+00:00')
        ->and($devices[0]->expiresAt)->toBe('2026-11-30T09:00:00+00:00')
        ->and($devices[1]->isCurrentDevice)->toBeFalse()
        ->and($devices[1]->lastUsedAt)->toBeNull()
        // An unknown device type reads as null rather than failing.
        ->and($devices[1]->deviceType)->toBeNull();

    assertSentTo('GET', 'user/trusted-devices', fn (Request $request): bool => ! $request->hasHeader('X-Team-ID')
        && $request->hasHeader('Authorization', 'Bearer access-1'));
});

it('lists no devices', function () {
    fakeApi(['user/trusted-devices' => ['data' => []]]);

    expect(sdk()->trustedDevices()->all())->toBe([]);
});

it('revokes one device', function () {
    fakeApi(['user/trusted-devices/13' => Http::response(null, 204)]);

    sdk()->trustedDevices()->destroy(13);

    assertSentTo('DELETE', 'user/trusted-devices/13', fn (Request $request): bool => ! $request->hasHeader('X-Team-ID'));
});

it('throws when the device is not the user\'s', function () {
    fakeApi(['user/trusted-devices/99' => Http::response(['message' => 'Not Found'], 404)]);

    sdk()->trustedDevices()->destroy(99);
})->throws(SupportManagerApiException::class, 'Not Found');

it('revokes every other device and returns how many', function () {
    fakeApi(['user/trusted-devices' => ['data' => ['revoked_count' => 2]]]);

    expect(sdk()->trustedDevices()->destroyOthers())->toBe(2);

    assertSentTo('DELETE', 'user/trusted-devices');
});

it('reads a refresh-token 403 as an API error', function () {
    fakeApi(['user/trusted-devices' => Http::response(['message' => 'Invalid ability provided.'], 403)]);

    sdk()->trustedDevices()->all();
})->throws(SupportManagerApiException::class, 'Invalid ability provided.');
