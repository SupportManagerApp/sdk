<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Documents;

/**
 * A folder organising documents into an unlimited-depth hierarchy.
 *
 * Mirrors the platform's `DocumentFolderResource` attributes. `parentId` is
 * null for root folders; the `*Count` fields are only present when the server
 * counts the relation.
 */
final readonly class DocumentFolder
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public ?int $teamId,
        public ?int $parentId,
        public ?int $documentsCount,
        public ?int $childrenCount,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            parentId: isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            documentsCount: isset($data['documents_count']) ? (int) $data['documents_count'] : null,
            childrenCount: isset($data['children_count']) ? (int) $data['children_count'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}
