<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Compliance;

use SupportManager\Sdk\Data\Hydration\Attributes\Can;
use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A driving licence verification record for an employee.
 *
 * Mirrors the platform's `DriversLicenceCheckResource` attributes. Sensitive
 * fields (`driversLicenceNumber`, `checkCode`, `checkResult`) are decrypted
 * server-side and only returned to authorised viewers; `notes` (the
 * reviewer's) is only sent to privileged users. `checkResultDocument` is the
 * uploaded file's name — fetch it via
 * {@see \SupportManager\Sdk\Resources\Compliance\DriversLicenceChecksResource::downloadCheckResultDocument()}.
 * `canUpdate`/`canDownload` are the server's `meta.can` flags for the
 * authenticated user (update is true only for the record's own employee).
 */
final readonly class DriversLicenceCheck
{
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?int $userId,
        #[From('user.name')]
        public ?string $userName,
        public ?string $driversLicenceNumber,
        public ?string $checkCode,
        public bool $declarationAccepted,
        public ?string $checkResult,
        public ?string $checkResultDocument,
        public ?string $notes,
        public ?string $createdAt,
        public ?string $updatedAt,
        #[Can('update')]
        public bool $canUpdate = false,
        #[Can('download')]
        public bool $canDownload = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
