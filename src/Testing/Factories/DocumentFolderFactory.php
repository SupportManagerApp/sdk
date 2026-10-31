<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake document folders.
 *
 * A read-only JSON:API collection (`document_folders`); the generic GET list
 * comes for free from the resource path. This factory answers the single-folder
 * route itself so `find()` carries `ancestors` — the seeded parent chain, root
 * first — as the platform's breadcrumb lookup does. States cover nesting
 * ({@see childOf()}) and personal folders ({@see personal()}).
 *
 * ```php
 * SupportManager::fake()->seed(DocumentFolderFactory::new()->count(3));
 * SupportManager::documentFolders()->query()->get();
 * SupportManager::documentFolders()->find(3)->ancestors;
 * ```
 */
final class DocumentFolderFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'documents/document-folders';
    }

    public function type(): string
    {
        return 'document_folders';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'name' => "Folder {$number}",
            'description' => 'Team files',
            'team_id' => 42,
            'scope' => 'team',
            'user_id' => null,
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

    /**
     * Make this a personal folder belonging to one team member.
     */
    public function personal(int $userId = 9): static
    {
        return $this->state(['scope' => 'personal', 'user_id' => $userId]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::get('documents/document-folders/{id}', static function (RecordStore $store, array $matches): mixed {
                $folder = $store->find('documents/document-folders', $matches[1]);

                if ($folder === null) {
                    return Http::response(['message' => 'Not found'], 404);
                }

                return Http::response(Json::item('document_folders', [
                    ...$folder,
                    'ancestors' => self::ancestors($store, $folder),
                ]));
            }),
        ];
    }

    /**
     * The folder's parent chain from the root down to its immediate parent.
     *
     * @param  array<string, mixed>  $folder
     * @return list<array{id: int, name: string}>
     */
    private static function ancestors(RecordStore $store, array $folder): array
    {
        $ancestors = [];
        $seen = [];
        $parentId = $folder['parent_id'] ?? null;

        while ($parentId !== null && ! isset($seen[(string) $parentId])) {
            $seen[(string) $parentId] = true;
            $parent = $store->find('documents/document-folders', (string) $parentId);

            if ($parent === null) {
                break;
            }

            array_unshift($ancestors, ['id' => (int) $parent['id'], 'name' => (string) ($parent['name'] ?? '')]);
            $parentId = $parent['parent_id'] ?? null;
        }

        return $ancestors;
    }
}
