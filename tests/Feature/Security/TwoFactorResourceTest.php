<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SupportManager\Sdk\Data\Security\BackupCodes;
use SupportManager\Sdk\Data\Security\TotpSetup;
use SupportManager\Sdk\Data\Security\TwoFactorStatus;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;

/*
 * Against src/Auth/Api/Http/Controllers/TwoFactorController.php,
 * TwoFactorStatusResource and TotpSetupResource on the platform.
 */

const EIGHT_BACKUP_CODES = ['ABCD-EFGH', 'JKLM-NPQR', 'STUV-WXYZ', '2345-6789', 'AB23-CD45', 'EF67-GH89', 'JK23-LM45', 'NP67-QR89'];

it('shows the two-factor status without a team header', function () {
    fakeApi(['user/two-factor' => ['data' => [
        'has_second_factor' => true,
        'totp_enabled' => true,
        'totp_enabled_at' => '2026-10-01T09:00:00+00:00',
        'backup_codes_remaining' => 7,
        'backup_codes_generated_at' => '2026-10-01T09:00:00+00:00',
        'second_factor_required_by' => null,
    ]]]);

    $status = sdk()->twoFactor()->show();

    expect($status)->toBeInstanceOf(TwoFactorStatus::class)
        ->and($status->hasSecondFactor)->toBeTrue()
        ->and($status->totpEnabled)->toBeTrue()
        ->and($status->totpEnabledAt)->toBe('2026-10-01T09:00:00+00:00')
        ->and($status->backupCodesRemaining)->toBe(7)
        ->and($status->backupCodesGeneratedAt)->toBe('2026-10-01T09:00:00+00:00')
        ->and($status->secondFactorRequiredBy)->toBeNull();

    assertSentTo('GET', 'user/two-factor', fn (Request $request): bool => ! $request->hasHeader('X-Team-ID'));
});

it('shows a user with no second factor and a deadline', function () {
    fakeApi(['user/two-factor' => ['data' => [
        'has_second_factor' => false,
        'totp_enabled' => false,
        'totp_enabled_at' => null,
        'backup_codes_remaining' => 0,
        'backup_codes_generated_at' => null,
        'second_factor_required_by' => '2026-11-14T00:00:00+00:00',
    ]]]);

    $status = sdk()->twoFactor()->show();

    expect($status->hasSecondFactor)->toBeFalse()
        ->and($status->totpEnabledAt)->toBeNull()
        ->and($status->secondFactorRequiredBy)->toBe('2026-11-14T00:00:00+00:00');
});

it('begins the authenticator setup', function () {
    fakeApi(['user/two-factor/totp' => ['data' => [
        'secret' => 'JBSWY3DPEHPK3PXP',
        'formatted_secret' => 'JBSW Y3DP EHPK 3PXP',
        'otpauth_uri' => 'otpauth://totp/SupportManager:sam%40example.com?secret=JBSWY3DPEHPK3PXP&issuer=SupportManager',
        'qr_code_data_uri' => 'data:image/svg+xml;base64,PD94bWwg',
        'expires_in' => 900,
    ]]]);

    $setup = sdk()->twoFactor()->beginTotpSetup();

    expect($setup)->toBeInstanceOf(TotpSetup::class)
        ->and($setup->secret)->toBe('JBSWY3DPEHPK3PXP')
        ->and($setup->formattedSecret)->toBe('JBSW Y3DP EHPK 3PXP')
        ->and($setup->otpauthUri)->toStartWith('otpauth://totp/')
        ->and($setup->qrCodeDataUri)->toStartWith('data:image/svg+xml;base64,')
        ->and($setup->expiresIn)->toBe(900);

    assertSentTo('POST', 'user/two-factor/totp', fn (Request $request): bool => ! $request->hasHeader('X-Team-ID'));
});

it('keeps the secret and backup codes out of debug dumps', function () {
    $setup = new TotpSetup('JBSWY3DPEHPK3PXP', 'JBSW Y3DP EHPK 3PXP', 'otpauth://totp/x?secret=JBSWY3DPEHPK3PXP', 'data:image/svg+xml;base64,PD94', 900);
    $codes = new BackupCodes(EIGHT_BACKUP_CODES);

    expect(print_r($setup, true))->not->toContain('JBSWY3DPEHPK3PXP')->toContain('900')
        ->and(print_r($codes, true))->not->toContain('ABCD-EFGH')->toContain('8 redacted');
});

it('refuses to begin a setup when the app is already enabled', function () {
    fakeApi(['user/two-factor/totp' => Http::response(['message' => 'Authenticator app is already enabled.'], 409)]);

    try {
        sdk()->twoFactor()->beginTotpSetup();
        $this->fail('Expected a SupportManagerApiException.');
    } catch (SupportManagerApiException $e) {
        expect($e->status)->toBe(409)
            ->and($e->getMessage())->toBe('Authenticator app is already enabled.');
    }
});

it('confirms the setup with a code and returns the backup codes', function () {
    fakeApi(['user/two-factor/totp/confirm' => [
        'message' => 'Authenticator app enabled successfully.',
        'data' => ['backup_codes' => EIGHT_BACKUP_CODES],
    ]]);

    $codes = sdk()->twoFactor()->confirmTotpSetup('123456');

    expect($codes)->toBeInstanceOf(BackupCodes::class)
        ->and($codes->codes)->toBe(EIGHT_BACKUP_CODES);

    assertSentTo('POST', 'user/two-factor/totp/confirm', fn (Request $request): bool => $request->data() === ['code' => '123456']);
});

it('surfaces a wrong code as a validation error on code', function () {
    fakeApi(['user/two-factor/totp/confirm' => Http::response([
        'message' => 'Invalid verification code. Please try again.',
        'errors' => ['code' => ['Invalid verification code. Please try again.']],
    ], 422)]);

    try {
        sdk()->twoFactor()->confirmTotpSetup('000000');
        $this->fail('Expected an ApiValidationException.');
    } catch (ApiValidationException $e) {
        expect($e->first('code'))->toBe('Invalid verification code. Please try again.');
    }
});

it('surfaces an expired setup as a validation error on code', function () {
    fakeApi(['user/two-factor/totp/confirm' => Http::response([
        'message' => 'Your authenticator setup has expired. Please start again.',
        'errors' => ['code' => ['Your authenticator setup has expired. Please start again.']],
    ], 422)]);

    sdk()->twoFactor()->confirmTotpSetup('123456');
})->throws(ApiValidationException::class, 'Your authenticator setup has expired. Please start again.');

it('surfaces the attempt limit as a 429', function () {
    fakeApi(['user/two-factor/totp/confirm' => Http::response(
        ['message' => 'Too many verification attempts. Please wait before trying again.'],
        429,
        ['Retry-After' => '840'],
    )]);

    try {
        sdk()->twoFactor()->confirmTotpSetup('123456');
        $this->fail('Expected a SupportManagerApiException.');
    } catch (SupportManagerApiException $e) {
        expect($e->status)->toBe(429)
            ->and($e->getMessage())->toBe('Too many verification attempts. Please wait before trying again.');
    }
});

it('disables the authenticator app with a code', function () {
    fakeApi(['user/two-factor/totp/disable' => ['message' => 'Authenticator app disabled.']]);

    sdk()->twoFactor()->disableTotp('123456');

    assertSentTo('POST', 'user/two-factor/totp/disable', fn (Request $request): bool => $request->data() === ['code' => '123456']
        && ! $request->hasHeader('X-Team-ID'));
});

it('refuses to disable an app that is not enabled', function () {
    fakeApi(['user/two-factor/totp/disable' => Http::response(['message' => 'Authenticator app is not enabled.'], 409)]);

    sdk()->twoFactor()->disableTotp('123456');
})->throws(SupportManagerApiException::class, 'Authenticator app is not enabled.');

it('regenerates the backup codes with a code', function () {
    fakeApi(['user/two-factor/backup-codes' => [
        'message' => 'Backup codes regenerated successfully.',
        'data' => ['backup_codes' => EIGHT_BACKUP_CODES],
    ]]);

    expect(sdk()->twoFactor()->regenerateBackupCodes('654321')->codes)->toBe(EIGHT_BACKUP_CODES);

    assertSentTo('POST', 'user/two-factor/backup-codes', fn (Request $request): bool => $request->data() === ['code' => '654321']);
});

it('never logs a body that carries the secret or the codes', function () {
    Log::spy();
    fakeApi([
        'user/two-factor/totp' => ['data' => ['secret' => 'JBSWY3DPEHPK3PXP', 'formatted_secret' => 'JBSW Y3DP EHPK 3PXP', 'otpauth_uri' => 'otpauth://totp/x', 'qr_code_data_uri' => 'data:image/svg+xml;base64,PD94', 'expires_in' => 900]],
        'user/two-factor/backup-codes' => ['message' => 'Backup codes regenerated successfully.', 'data' => ['backup_codes' => EIGHT_BACKUP_CODES]],
    ]);

    sdk()->twoFactor()->beginTotpSetup();
    sdk()->twoFactor()->regenerateBackupCodes('654321');

    // Only an error response is logged, and these come back only on success.
    Log::shouldNotHaveReceived('warning');
});
