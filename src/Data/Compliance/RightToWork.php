<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Compliance;

/**
 * A right-to-work record for an employee — primary/supporting documents and
 * the government share code.
 *
 * Mirrors the platform's `RightToWorkResource` attributes. `shareCode` is
 * decrypted server-side and only returned to authorised viewers.
 */
final readonly class RightToWork
{
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?int $userId,
        public ?string $userName,
        public ?string $primaryDocumentType,
        public ?string $primaryDocument,
        public ?string $supportingDocument,
        public ?string $shareCode,
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
            primaryDocumentType: isset($data['primary_document_type']) ? (string) $data['primary_document_type'] : null,
            primaryDocument: isset($data['primary_document']) ? (string) $data['primary_document'] : null,
            supportingDocument: isset($data['supporting_document']) ? (string) $data['supporting_document'] : null,
            shareCode: isset($data['share_code']) ? (string) $data['share_code'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}
