<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Security;

use SensitiveParameter;
use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A fresh set of single-use backup codes, from confirming the authenticator
 * app setup or regenerating the codes. Each can stand in for an authenticator
 * code at device verification, and the set replaces any codes issued before.
 *
 * The platform returns them once and never again, so show them for the user
 * to save, and keep them out of logs, caches and crash reports. `var_dump()`
 * and `dump()` show only how many there are.
 */
final readonly class BackupCodes
{
    /**
     * @param  list<string>  $codes
     */
    public function __construct(
        #[From('backup_codes')]
        #[ListOf('string')]
        #[SensitiveParameter]
        public array $codes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['codes' => '['.count($this->codes).' redacted]'];
    }

    /**
     * @param  array<string, mixed>  $data  The plain `data` object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
