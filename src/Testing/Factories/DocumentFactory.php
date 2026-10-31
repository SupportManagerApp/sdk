<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;

/**
 * Fake uploaded documents.
 *
 * JSON:API collection (`documents`), so generic CRUD comes for free from the
 * resource path. This factory adds the non-RESTful `download()` action, which
 * returns a flat `{data: {download_url, filename, expires_at}}` payload the
 * resource hands back raw.
 *
 * ```php
 * SupportManager::fake()->seed(DocumentFactory::new()->count(3));
 * SupportManager::documents()->download(1)->json('data.download_url');
 * ```
 */
final class DocumentFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'documents/documents';
    }

    public function type(): string
    {
        return 'documents';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'name' => "Document {$number}",
            'scope' => 'personal',
            'team_id' => $this->teamId(),
            'user_id' => 9,
            'user' => ['id' => 9, 'name' => 'Ada'],
            'document_folder_id' => 3,
            'document_folder' => ['id' => 3, 'name' => 'Contracts'],
            'uploaded_by' => ['id' => 1, 'name' => 'Grace'],
            'created_at' => '2026-01-01T09:00:00Z',
            'updated_at' => '2026-01-01T09:00:00Z',
        ];
    }

    /**
     * A team document shared with the whole team (no assigned user).
     */
    public function team(): static
    {
        return $this->state(['scope' => 'team', 'user_id' => null, 'user' => null]);
    }

    /**
     * File the document in a specific folder.
     */
    public function inFolder(int $id, string $name): static
    {
        return $this->state([
            'document_folder_id' => $id,
            'document_folder' => ['id' => $id, 'name' => $name],
        ]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::downloadLink(
                'documents/documents/{id}/download',
                'name',
                static fn (array $document): string => "https://s3.test/signed/{$document['id']}.pdf",
            ),
        ];
    }
}
