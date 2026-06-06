<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Testing\Factory;

/**
 * Fake document folders.
 *
 * A read-only JSON:API collection (`document-folders`); generic GET list +
 * `find()` come for free from the resource path.
 *
 * ```php
 * SupportManager::fake()->seed(DocumentFolderFactory::new()->count(3));
 * SupportManager::documentFolders()->query()->get();
 * ```
 */
final class DocumentFolderFactory extends Factory
{
    public function path(): string
    {
        return 'documents/document-folders';
    }

    public function type(): string
    {
        return 'document-folders';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'name' => "Folder {$number}",
            'description' => 'Team files',
            'team_id' => 42,
            'parent_id' => null,
            'documents_count' => 4,
            'children_count' => 2,
            'created_at' => '2026-01-01T09:00:00Z',
            'updated_at' => '2026-01-01T09:00:00Z',
        ];
    }

    /**
     * Nest this folder beneath a parent folder.
     */
    public function childOf(int $parentId): static
    {
        return $this->state(['parent_id' => $parentId]);
    }
}
