<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Documents;

use SupportManager\Sdk\Enums\Documents\DocumentScope;

/**
 * An uploaded document file, optionally assigned to a user and filed in a
 * folder.
 *
 * Mirrors the platform's `DocumentResource` attributes. `scope` is the typed
 * enum (null for a value the SDK does not know yet); a team document with no
 * `userId` is shared with the whole team. The file itself is never exposed —
 * fetch it via {@see \SupportManager\Sdk\Resources\Documents\DocumentsResource::download()}.
 * The `user`,
 * `documentFolder` and `uploadedBy` relations are only populated when the
 * server includes them; their flattened names mirror the resource's nested
 * arrays.
 *
 * Note: distinct from {@see \SupportManager\Sdk\Http\Document}, the JSON:API
 * envelope — this is the Documents-module DTO.
 */
final readonly class Document
{
    public function __construct(
        public string $id,
        public string $name,
        public ?DocumentScope $scope,
        public ?int $teamId,
        public ?int $userId,
        public ?string $userName,
        public ?int $documentFolderId,
        public ?string $documentFolderName,
        public ?int $uploadedById,
        public ?string $uploadedByName,
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

        /** @var array{id?: mixed, name?: mixed} $folder */
        $folder = is_array($data['document_folder'] ?? null) ? $data['document_folder'] : [];

        /** @var array{id?: mixed, name?: mixed} $uploadedBy */
        $uploadedBy = is_array($data['uploaded_by'] ?? null) ? $data['uploaded_by'] : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            scope: isset($data['scope']) ? DocumentScope::tryFrom((string) $data['scope']) : null,
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            userName: isset($user['name']) ? (string) $user['name'] : null,
            documentFolderId: isset($data['document_folder_id']) ? (int) $data['document_folder_id'] : null,
            documentFolderName: isset($folder['name']) ? (string) $folder['name'] : null,
            uploadedById: isset($uploadedBy['id']) ? (int) $uploadedBy['id'] : null,
            uploadedByName: isset($uploadedBy['name']) ? (string) $uploadedBy['name'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}
