<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\Security\TrustedDevice;
use SupportManager\Sdk\Enums\Security\SocialProvider;
use SupportManager\Sdk\Enums\Security\TrustedDeviceType;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Testing\Factories\SocialAccountFactory;
use SupportManager\Sdk\Testing\Factories\TrustedDeviceFactory;
use SupportManager\Sdk\Testing\Factories\TwoFactorStatusFactory;

// Host-style tests: seed factories, call the SDK, assert on DTOs. No Http::fake().

it('lists trusted devices most recently used first, flagging the current one', function () {
    SupportManager::fake()->seed(
        TrustedDeviceFactory::new()->count(2),
        TrustedDeviceFactory::new()->current(),
    );

    $devices = SupportManager::trustedDevices()->all();

    expect($devices)->toHaveCount(3)
        ->and($devices[0])->toBeInstanceOf(TrustedDevice::class)
        ->and($devices[0]->isCurrentDevice)->toBeTrue()
        ->and($devices[0]->deviceType)->toBe(TrustedDeviceType::Mobile)
        ->and($devices[1]->isCurrentDevice)->toBeFalse()
        ->and($devices[1]->deviceType)->toBe(TrustedDeviceType::Desktop);
});

it('revokes one trusted device, and refuses one that is not stored', function () {
    SupportManager::fake()->seed(TrustedDeviceFactory::new()->count(2));

    SupportManager::trustedDevices()->destroy(1);

    expect(SupportManager::trustedDevices()->all())->toHaveCount(1)
        ->and(fn () => SupportManager::trustedDevices()->destroy(99))->toThrow(SupportManagerApiException::class);
});

it('revokes every trusted device but the current one', function () {
    SupportManager::fake()->seed(
        TrustedDeviceFactory::new()->current(),
        TrustedDeviceFactory::new()->count(2),
    );

    expect(SupportManager::trustedDevices()->destroyOthers())->toBe(2);

    $devices = SupportManager::trustedDevices()->all();
    expect($devices)->toHaveCount(1)
        ->and($devices[0]->isCurrentDevice)->toBeTrue();
});

it('lists linked accounts and every provider with whether it is linked', function () {
    SupportManager::fake()->seed(SocialAccountFactory::new()->provider(SocialProvider::Google));

    $accounts = SupportManager::socialAccounts()->all();
    $providers = SupportManager::socialAccounts()->providers();

    expect($accounts)->toHaveCount(1)
        ->and($accounts[0]->provider)->toBe(SocialProvider::Google)
        ->and($accounts[0]->providerEmail)->toBe('sam@google.test')
        ->and(array_map(fn ($option) => [$option->provider, $option->linked], $providers))->toBe([
            [SocialProvider::Google, true],
            [SocialProvider::Microsoft, false],
            [SocialProvider::Apple, false],
        ]);
});

it('links and unlinks a provider account', function () {
    SupportManager::fake()->seed(SocialAccountFactory::new()->count(0));

    $account = SupportManager::socialAccounts()->link(SocialProvider::Apple, 'identity-token');

    expect($account?->provider)->toBe(SocialProvider::Apple)
        ->and(SupportManager::socialAccounts()->all())->toHaveCount(1);

    SupportManager::socialAccounts()->destroy(SocialProvider::Apple);

    expect(SupportManager::socialAccounts()->all())->toBe([])
        ->and(fn () => SupportManager::socialAccounts()->destroy(SocialProvider::Apple))->toThrow(SupportManagerApiException::class, 'Not found');
});

it('refuses a second account for a linked provider, and a token it cannot verify', function () {
    SupportManager::fake()->seed(SocialAccountFactory::new()->provider(SocialProvider::Google));

    expect(fn () => SupportManager::socialAccounts()->link(SocialProvider::Google, 'ya29.token'))
        ->toThrow(ApiValidationException::class, 'You already have a Google account linked.')
        ->and(fn () => SupportManager::socialAccounts()->link(SocialProvider::Microsoft, SocialAccountFactory::INVALID_TOKEN))
        ->toThrow(ApiValidationException::class, 'The provided Microsoft token could not be verified.');
});

it('sets up the authenticator app and reports it enabled', function () {
    SupportManager::fake()->seed(TwoFactorStatusFactory::new()->requiredBy('2026-11-14T00:00:00+00:00'));

    expect(SupportManager::twoFactor()->show()->secondFactorRequiredBy)->toBe('2026-11-14T00:00:00+00:00');

    $setup = SupportManager::twoFactor()->beginTotpSetup();
    expect($setup->secret)->toBe(TwoFactorStatusFactory::SECRET)
        ->and($setup->formattedSecret)->toBe('JBSW Y3DP EHPK 3PXP')
        ->and($setup->expiresIn)->toBe(900);

    $codes = SupportManager::twoFactor()->confirmTotpSetup(TwoFactorStatusFactory::VALID_CODE);
    expect($codes->codes)->toHaveCount(8);

    $status = SupportManager::twoFactor()->show();
    expect($status->totpEnabled)->toBeTrue()
        ->and($status->hasSecondFactor)->toBeTrue()
        ->and($status->backupCodesRemaining)->toBe(8)
        ->and($status->secondFactorRequiredBy)->toBeNull();
});

it('refuses to confirm with a wrong code or without a setup begun', function () {
    SupportManager::fake()->seed(TwoFactorStatusFactory::new());

    expect(fn () => SupportManager::twoFactor()->confirmTotpSetup(TwoFactorStatusFactory::VALID_CODE))
        ->toThrow(ApiValidationException::class, 'Your authenticator setup has expired. Please start again.');

    SupportManager::twoFactor()->beginTotpSetup();

    expect(fn () => SupportManager::twoFactor()->confirmTotpSetup('000000'))
        ->toThrow(ApiValidationException::class, 'Invalid verification code. Please try again.')
        ->and(SupportManager::twoFactor()->show()->totpEnabled)->toBeFalse();
});

it('refuses to begin a setup when the app is already enabled', function () {
    SupportManager::fake()->seed(TwoFactorStatusFactory::new()->enabled());

    try {
        SupportManager::twoFactor()->beginTotpSetup();
        $this->fail('Expected a 409.');
    } catch (SupportManagerApiException $e) {
        expect($e->status)->toBe(409)
            ->and($e->getMessage())->toBe('Authenticator app is already enabled.');
    }
});

it('regenerates backup codes with a valid code', function () {
    SupportManager::fake()->seed(TwoFactorStatusFactory::new()->enabled(backupCodesRemaining: 2));

    expect(SupportManager::twoFactor()->regenerateBackupCodes(TwoFactorStatusFactory::VALID_CODE)->codes)->toHaveCount(8)
        ->and(SupportManager::twoFactor()->show()->backupCodesRemaining)->toBe(8)
        ->and(fn () => SupportManager::twoFactor()->regenerateBackupCodes('12345'))->toThrow(ApiValidationException::class, 'Please enter a 6-digit code.');
});

it('disables the app and signs out the other trusted devices', function () {
    SupportManager::fake()->seed(
        TwoFactorStatusFactory::new()->enabled(),
        TrustedDeviceFactory::new()->current(),
        TrustedDeviceFactory::new()->count(2),
    );

    SupportManager::twoFactor()->disableTotp(TwoFactorStatusFactory::VALID_CODE);

    $status = SupportManager::twoFactor()->show();
    expect($status->totpEnabled)->toBeFalse()
        ->and($status->hasSecondFactor)->toBeFalse()
        ->and($status->backupCodesRemaining)->toBe(8)
        ->and(SupportManager::trustedDevices()->all())->toHaveCount(1)
        ->and(fn () => SupportManager::twoFactor()->disableTotp(TwoFactorStatusFactory::VALID_CODE))
        ->toThrow(SupportManagerApiException::class, 'Authenticator app is not enabled.');
});

it('keeps a passkey user\'s second factor when the app is disabled', function () {
    SupportManager::fake()->seed(TwoFactorStatusFactory::new()->enabled()->withPasskey());

    SupportManager::twoFactor()->disableTotp(TwoFactorStatusFactory::VALID_CODE);

    expect(SupportManager::twoFactor()->show()->hasSecondFactor)->toBeTrue();
});
