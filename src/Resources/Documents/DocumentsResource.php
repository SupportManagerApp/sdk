<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Documents;

use SupportManager\Sdk\Data\Documents\Document;
use SupportManager\Sdk\Http\DownloadLink;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Concerns\Findable;
use SupportManager\Sdk\Resources\Concerns\Listable;
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
 * SupportManager::documents()->download(7)->url;
 * ```
 *
 * @extends Resource<Document>
 */
final class DocumentsResource extends Resource
{
    /** @use Findable<Document> */
    use Findable;

    /** @use Listable<Document> */
    use Listable;

    /**
     * A short-lived link to a document's file. The platform answers with
     * `{data: {download_url, filename, expires_at}}` rather than the file, so
     * this returns a {@see DownloadLink}; the URL is a signed link that expires
     * after five minutes. An error response (e.g. a 404 for a document the
     * user cannot see) throws.
     */
    public function download(string|int $id): DownloadLink
    {
        return $this->downloadLink("documents/documents/{$id}/download");
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
