<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Compliance;

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
        public ?string $userName,
        public ?string $dateOfIssue,
        public ?string $certificateNumber,
        public ?string $certificate,
        public ?string $checkResult,
        public ?string $notes,
        public ?string $createdAt,
        public ?string $updatedAt,
        public bool $canUpdate = false,
        public bool $canDownload = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id?: mixed, name?: mixed} $user */
        $user = is_array($data['user'] ?? null) ? $data['user'] : [];

        /** @var array{update?: mixed, download?: mixed} $can */
        $can = is_array($data['meta']['can'] ?? null) ? $data['meta']['can'] : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            userName: isset($user['name']) ? (string) $user['name'] : null,
            dateOfIssue: isset($data['date_of_issue']) ? (string) $data['date_of_issue'] : null,
            certificateNumber: isset($data['certificate_number']) ? (string) $data['certificate_number'] : null,
            certificate: isset($data['certificate']) ? (string) $data['certificate'] : null,
            checkResult: isset($data['check_result']) ? (string) $data['check_result'] : null,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            canUpdate: (bool) ($can['update'] ?? false),
            canDownload: (bool) ($can['download'] ?? false),
        );
    }
}
