<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Documents;

use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Hydrator;
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
        #[From('user.name')]
        public ?string $userName,
        public ?int $documentFolderId,
        #[From('document_folder.name')]
        public ?string $documentFolderName,
        #[From('uploaded_by.id')]
        public ?int $uploadedById,
        #[From('uploaded_by.name')]
        public ?string $uploadedByName,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
