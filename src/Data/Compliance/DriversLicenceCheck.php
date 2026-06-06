<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Compliance;

/**
 * A driving licence verification record for an employee.
 *
 * Mirrors the platform's `DriversLicenceCheckResource` attributes. Sensitive
 * fields (`driversLicenceNumber`, `checkCode`, `checkResult`, `notes`) are
 * decrypted server-side and only returned to authorised viewers.
 */
final readonly class DriversLicenceCheck
{
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?int $userId,
        public ?string $userName,
        public ?string $driversLicenceNumber,
        public ?string $checkCode,
        public bool $declarationAccepted,
        public ?string $checkResult,
        public ?string $checkResultDocument,
        public ?string $notes,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id?: mixed, name?: mixed} $user */
        $user = is_array($data['user'] ?? null) ? $data['user'] : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            userName: isset($user['name']) ? (string) $user['name'] : null,
            driversLicenceNumber: isset($data['drivers_licence_number']) ? (string) $data['drivers_licence_number'] : null,
            checkCode: isset($data['check_code']) ? (string) $data['check_code'] : null,
            declarationAccepted: (bool) ($data['declaration_accepted'] ?? false),
            checkResult: isset($data['check_result']) ? (string) $data['check_result'] : null,
            checkResultDocument: isset($data['check_result_document']) ? (string) $data['check_result_document'] : null,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}
