<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Documents;

use SupportManager\Sdk\Data\Hydration\Hydrator;
use SupportManager\Sdk\Enums\Documents\DocumentScope;

/**
 * A folder organising documents into an unlimited-depth hierarchy.
 *
 * Mirrors the platform's `DocumentFolderResource` attributes. `parentId` is
 * null for root folders; the `*Count` fields are only present when the server
 * counts the relation. `scope` is the typed enum (null for a value the SDK does
 * not know yet) and `userId` the owner of a personal folder (null for team
 * folders). `ancestors` — the parent folders from the root down to the
 * immediate parent, for breadcrumbs — is only returned by a single-folder
 * `find()`, and is empty for a root folder or a listed one.
 */
final readonly class DocumentFolder
{
    /**
     * @param  list<array{id: int|null, name: string|null}>  $ancestors
     */
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
        public ?DocumentScope $scope = null,
        public ?int $userId = null,
        public array $ancestors = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data, ['ancestors' => self::ancestors($data['ancestors'] ?? null)]);
    }

    /**
     * @return list<array{id: int|null, name: string|null}>
     */
    private static function ancestors(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $ancestors = [];

        foreach ($value as $ancestor) {
            if (! is_array($ancestor)) {
                continue;
            }

            $ancestors[] = [
                'id' => isset($ancestor['id']) ? (int) $ancestor['id'] : null,
                'name' => isset($ancestor['name']) ? (string) $ancestor['name'] : null,
            ];
        }

        return $ancestors;
    }
}
