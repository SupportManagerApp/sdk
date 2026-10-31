<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Security;

use SensitiveParameter;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A pending authenticator app (TOTP) setup, from `POST user/two-factor/totp`.
 * Mirrors the platform's `TotpSetupResource`.
 *
 * Show `qrCodeDataUri` (an SVG data URI) to scan from another device, open
 * `otpauthUri` to hand the secret to an authenticator app on this one, or
 * display `formattedSecret` for manual entry. The platform holds the secret
 * for `expiresIn` seconds, waiting for a code to confirm it.
 *
 * Every field but `expiresIn` carries the secret, so keep them out of logs,
 * caches and crash reports. `var_dump()` and `dump()` show them redacted.
 */
final readonly class TotpSetup
{
    public function __construct(
        #[SensitiveParameter]
        public string $secret,
        #[SensitiveParameter]
        public string $formattedSecret,
        #[SensitiveParameter]
        public string $otpauthUri,
        #[SensitiveParameter]
        public string $qrCodeDataUri,
        public int $expiresIn,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'secret' => '[redacted]',
            'formattedSecret' => '[redacted]',
            'otpauthUri' => '[redacted]',
            'qrCodeDataUri' => '[redacted]',
            'expiresIn' => $this->expiresIn,
        ];
    }

    /**
     * @param  array<string, mixed>  $data  The plain `data` object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
