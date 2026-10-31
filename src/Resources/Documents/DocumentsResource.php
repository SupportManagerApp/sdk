<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Documents;

use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Data\Documents\Document;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Uploaded document files for the current team.
 *
 * The collection (`query()`, `find()`, `all()`) plus a `download()` action that
 * returns a short-lived download URL rather than a hydratable resource.
 * Visibility is scoped server-side: everyone sees their own personal documents
 * and the team documents shared with them; privileged users also see every
 * colleague's personal documents. Filter by `scope` (`personal` / `team`),
 * `document_folder_id`, `user_id` and `name` (partial).
 *
 * ```php
 * SupportManager::documents()->query()->filter('name', 'contract')->get();
 * SupportManager::documents()->download(7)->json('data.download_url');
 * ```
 *
 * @extends \SupportManager\Sdk\Resources\Resource<Document>
 */
final class DocumentsResource extends Resource
{
    /**
     * Fetch the download payload for a document. The platform returns a plain
     * JSON body — `{data: {download_url, filename, expires_at}}` — not a JSON:API
     * resource, so the raw {@see Response} is handed back untouched. Read it via
     * `->json('data.download_url')` (the URL is a short-lived signed link).
     */
    public function download(string|int $id): Response
    {
        return $this->get("documents/documents/{$id}/download");
    }

    protected function path(): string
    {
        return 'documents/documents';
    }

    protected function hydrate(ResourceObject $object): Document
    {
        return Document::fromArray($object->flat());
    }
}
