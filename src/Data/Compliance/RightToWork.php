<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Compliance;

use SupportManager\Sdk\Data\Hydration\Attributes\Can;
use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A right-to-work record for an employee — primary/supporting documents and
 * the government share code.
 *
 * Mirrors the platform's `RightToWorkResource` attributes. `shareCode` is
 * decrypted server-side and only returned to authorised viewers. The
 * `*Document` fields are the uploaded files' names — fetch them via
 * {@see \SupportManager\Sdk\Resources\Compliance\RightToWorkResource::downloadPrimaryDocument()}
 * and `downloadSupportingDocument()`. `canUpdate`/`canDownload` are the
 * server's `meta.can` flags for the authenticated user (update is true only
 * for the record's own employee).
 */
final readonly class RightToWork
{
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?int $userId,
        #[From('user.name')]
        public ?string $userName,
        public ?string $primaryDocumentType,
        public ?string $primaryDocument,
        public ?string $supportingDocument,
        public ?string $shareCode,
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
