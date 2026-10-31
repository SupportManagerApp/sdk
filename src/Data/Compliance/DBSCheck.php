<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Compliance;

use SupportManager\Sdk\Data\Hydration\Attributes\Can;
use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A DBS (Disclosure and Barring Service) check record for an employee.
 *
 * Mirrors the platform's `DBSCheckResource` attributes. Sensitive fields
 * (`certificateNumber`, `checkResult`) are decrypted server-side and only
 * returned to authorised viewers; `notes` (the reviewer's) is only sent to
 * privileged users. `certificate` is the uploaded file's name — fetch it via
 * {@see \SupportManager\Sdk\Resources\Compliance\DBSChecksResource::downloadCertificate()}.
 * `canUpdate`/`canDownload` are the server's `meta.can` flags for the
 * authenticated user (update is true only for the record's own employee).
 */
final readonly class DBSCheck
{
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?int $userId,
        #[From('user.name')]
        public ?string $userName,
        public ?string $dateOfIssue,
        public ?string $certificateNumber,
        public ?string $certificate,
        public ?string $checkResult,
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
