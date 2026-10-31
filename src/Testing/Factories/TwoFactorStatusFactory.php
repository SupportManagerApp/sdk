<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake two-factor status (a flat singleton at `user/two-factor`), with the
 * authenticator app flow TwoFactorController runs:
 *
 * - `beginTotpSetup()` holds the fixed secret {@see self::SECRET} as pending,
 *   or answers 409 when the app is already enabled.
 * - `confirmTotpSetup()` takes {@see self::VALID_CODE}, enables the app and
 *   answers eight backup codes. Another code, or no setup pending, is a 422 on
 *   `code`; an enabled app is a 409.
 * - `disableTotp()` and `regenerateBackupCodes()` take {@see self::VALID_CODE}
 *   too, and answer 409 when the app is not enabled. Disabling also removes
 *   every seeded trusted device but the current one, as the platform does.
 *
 * ```php
 * SupportManager::fake()->seed(TwoFactorStatusFactory::new());
 * SupportManager::twoFactor()->beginTotpSetup()->secret;                  // TwoFactorStatusFactory::SECRET
 * SupportManager::twoFactor()->confirmTotpSetup(TwoFactorStatusFactory::VALID_CODE)->codes;
 * SupportManager::twoFactor()->show()->totpEnabled;                        // true
 * ```
 */
final class TwoFactorStatusFactory extends Factory implements ProvidesFakeRoutes
{
    /**
     * The authenticator code the fake accepts.
     */
    public const string VALID_CODE = '123456';

    /**
     * The secret every fake setup hands out.
     */
    public const string SECRET = 'JBSWY3DPEHPK3PXP';

    /**
     * Where the fake holds a begun but unconfirmed setup.
     */
    private const string PENDING_PATH = 'user/two-factor/totp';

    private const string ENABLED_AT = '2026-10-31T09:00:00+00:00';

    public function path(): string
    {
        return 'user/two-factor';
    }

    public function definition(int $index): array
    {
        return [
            'has_second_factor' => false,
            'totp_enabled' => false,
            'totp_enabled_at' => null,
            'backup_codes_remaining' => 0,
            'backup_codes_generated_at' => null,
            'second_factor_required_by' => null,
        ];
    }

    /**
     * The authenticator app is enabled, with a full set of backup codes.
     */
    public function enabled(int $backupCodesRemaining = 8): static
    {
        return $this->state([
            'has_second_factor' => true,
            'totp_enabled' => true,
            'totp_enabled_at' => '2026-10-01T09:00:00+00:00',
            'backup_codes_remaining' => $backupCodesRemaining,
            'backup_codes_generated_at' => '2026-10-01T09:00:00+00:00',
        ]);
    }

    /**
     * No second factor yet, and the web will insist on one after `$deadline`.
     */
    public function requiredBy(string $deadline): static
    {
        return $this->state(['second_factor_required_by' => $deadline]);
    }

    /**
     * The user has a passkey, so they keep a second factor without the app.
     */
    public function withPasskey(): static
    {
        return $this->state(['has_second_factor' => true, '_has_passkey' => true]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::post('user/two-factor/totp', static function (RecordStore $store): mixed {
                if (self::status($store)['totp_enabled'] === true) {
                    return Json::message('Authenticator app is already enabled.', 409);
                }

                $store->put(self::PENDING_PATH, [['secret' => self::SECRET]]);

                return Http::response(Json::flat([
                    'secret' => self::SECRET,
                    'formatted_secret' => mb_trim(chunk_split(self::SECRET, 4, ' ')),
                    'otpauth_uri' => 'otpauth://totp/SupportManager:sam%40example.com?secret='.self::SECRET.'&issuer=SupportManager',
                    'qr_code_data_uri' => 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg"/>'),
                    'expires_in' => 900,
                ]));
            }),

            FakeRoute::post('user/two-factor/totp/confirm', static function (RecordStore $store, array $matches, Request $request): mixed {
                if (self::status($store)['totp_enabled'] === true) {
                    return Json::message('Authenticator app is already enabled.', 409);
                }

                if ($store->all(self::PENDING_PATH) === []) {
                    return Json::validation(['code' => 'Your authenticator setup has expired. Please start again.']);
                }

                if (($refusal = self::refuseCode($request)) !== null) {
                    return $refusal;
                }

                $store->truncate(self::PENDING_PATH);
                self::save($store, [
                    'has_second_factor' => true,
                    'totp_enabled' => true,
                    'totp_enabled_at' => self::ENABLED_AT,
                    'second_factor_required_by' => null,
                ]);

                return self::backupCodes($store, 'Authenticator app enabled successfully.');
            }),

            FakeRoute::post('user/two-factor/totp/disable', static function (RecordStore $store, array $matches, Request $request): mixed {
                $status = self::status($store);

                if ($status['totp_enabled'] !== true) {
                    return Json::message('Authenticator app is not enabled.', 409);
                }

                if (($refusal = self::refuseCode($request)) !== null) {
                    return $refusal;
                }

                self::save($store, [
                    'has_second_factor' => ($status['_has_passkey'] ?? false) === true,
                    'totp_enabled' => false,
                    'totp_enabled_at' => null,
                ]);

                // The platform signs the user out everywhere else.
                $store->put('user/trusted-devices', array_values(array_filter(
                    $store->all('user/trusted-devices'),
                    static fn (array $device): bool => ($device['is_current_device'] ?? false) === true,
                )));

                return Json::message('Authenticator app disabled.');
            }),

            FakeRoute::post('user/two-factor/backup-codes', static function (RecordStore $store, array $matches, Request $request): mixed {
                if (self::status($store)['totp_enabled'] !== true) {
                    return Json::message('Authenticator app is not enabled.', 409);
                }

                return self::refuseCode($request) ?? self::backupCodes($store, 'Backup codes regenerated successfully.');
            }),
        ];
    }

    /**
     * The stored status, or the default one when nothing is seeded.
     *
     * @return array<string, mixed>
     */
    private static function status(RecordStore $store): array
    {
        return $store->all('user/two-factor')[0] ?? (new self)->definition(0);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private static function save(RecordStore $store, array $changes): void
    {
        $store->put('user/two-factor', [[...self::status($store), ...$changes]]);
    }

    /**
     * The 422 for a code the fake does not accept, or null for the valid one.
     */
    private static function refuseCode(Request $request): ?PromiseInterface
    {
        $code = $request->data()['code'] ?? null;

        if (! is_string($code) || mb_strlen($code) !== 6) {
            return Json::validation(['code' => 'Please enter a 6-digit code.']);
        }

        return $code === self::VALID_CODE
            ? null
            : Json::validation(['code' => 'Invalid verification code. Please try again.']);
    }

    /**
     * Replace the backup codes with a fresh eight and answer them.
     */
    private static function backupCodes(RecordStore $store, string $message): PromiseInterface
    {
        self::save($store, [
            'backup_codes_remaining' => 8,
            'backup_codes_generated_at' => self::ENABLED_AT,
        ]);

        return Http::response([
            'message' => $message,
            'data' => ['backup_codes' => array_map(
                static fn (int $number): string => sprintf('CODE-%04d', $number),
                range(1, 8),
            )],
        ]);
    }
}
