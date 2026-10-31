<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Security;

use SensitiveParameter;
use SupportManager\Sdk\Data\Security\BackupCodes;
use SupportManager\Sdk\Data\Security\TotpSetup;
use SupportManager\Sdk\Data\Security\TwoFactorStatus;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Concerns\Untenanted;
use SupportManager\Sdk\Resources\Resource;

/**
 * The authenticated user's second factor: the authenticator app (TOTP) and its
 * backup codes, as on the web profile. User-scoped, so no `X-Team-ID` goes out.
 * Passkeys count towards {@see TwoFactorStatus::$hasSecondFactor} but cannot be
 * registered or removed over the API.
 *
 * Setup is two steps: {@see self::beginTotpSetup()} returns a secret the
 * platform holds for a few minutes, and {@see self::confirmTotpSetup()} with a
 * code from the app enables it and returns the backup codes. Disabling and
 * regenerating the codes also take a current code from the app; none of these
 * asks for the password.
 *
 * The platform answers:
 * - 409 (a {@see \SupportManager\Sdk\Exceptions\SupportManagerApiException}
 *   with status 409) when the app is already enabled (begin, confirm) or not
 *   enabled (disable, regenerate);
 * - 422 (an {@see \SupportManager\Sdk\Exceptions\ApiValidationException} on
 *   `code`) for a wrong code, a code that is not six characters, or a setup
 *   that was never begun or has expired;
 * - 429 after five wrong codes within 15 minutes, the same limit as the web,
 *   with a `Retry-After` header.
 *
 * The secret and backup codes come back in {@see TotpSetup} and
 * {@see BackupCodes}: show them, never log or cache them.
 *
 * ```php
 * $setup = SupportManager::twoFactor()->beginTotpSetup();      // $setup->qrCodeDataUri, $setup->otpauthUri
 * $codes = SupportManager::twoFactor()->confirmTotpSetup('123456')->codes;
 * SupportManager::twoFactor()->show()->backupCodesRemaining;
 * SupportManager::twoFactor()->disableTotp('123456');
 * ```
 *
 * @extends Resource<TwoFactorStatus>
 */
final class TwoFactorResource extends Resource
{
    use Untenanted;

    /**
     * Which second factors the user has, and how many backup codes remain
     * (`GET user/two-factor`).
     */
    public function show(): TwoFactorStatus
    {
        return TwoFactorStatus::fromArray($this->raw($this->get($this->path())));
    }

    /**
     * Begin the authenticator app setup (`POST user/two-factor/totp`). Nothing
     * changes on the account until {@see self::confirmTotpSetup()}; beginning
     * again replaces the pending secret. 409 when the app is already enabled.
     */
    public function beginTotpSetup(): TotpSetup
    {
        return TotpSetup::fromArray($this->raw($this->post("{$this->path()}/totp")));
    }

    /**
     * Confirm the pending setup with a code from the authenticator app
     * (`POST user/two-factor/totp/confirm`), enabling it. Returns the user's
     * new backup codes, which replace any earlier ones and are shown only
     * this once.
     */
    public function confirmTotpSetup(#[SensitiveParameter] string $code): BackupCodes
    {
        return BackupCodes::fromArray($this->raw($this->post("{$this->path()}/totp/confirm", ['code' => $code])));
    }

    /**
     * Disable the authenticator app with a current code from it
     * (`POST user/two-factor/totp/disable`). As on the web, the platform then
     * signs the user out everywhere else: it revokes every API token but this
     * device's and every trusted device but this one. Backup codes stay.
     */
    public function disableTotp(#[SensitiveParameter] string $code): void
    {
        $this->throwForErrorResponse($this->post("{$this->path()}/totp/disable", ['code' => $code]));
    }

    /**
     * Replace the backup codes with a new set, given a current authenticator
     * code (`POST user/two-factor/backup-codes`). The old codes stop working,
     * and the new ones are shown only this once.
     */
    public function regenerateBackupCodes(#[SensitiveParameter] string $code): BackupCodes
    {
        return BackupCodes::fromArray($this->raw($this->post("{$this->path()}/backup-codes", ['code' => $code])));
    }

    protected function path(): string
    {
        return 'user/two-factor';
    }

    protected function hydrate(ResourceObject $object): TwoFactorStatus
    {
        return TwoFactorStatus::fromArray($object->flat());
    }
}
