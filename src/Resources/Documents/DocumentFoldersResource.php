<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Documents;

use SupportManager\Sdk\Data\Documents\DocumentFolder;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * The team's document folders (read-only).
 *
 * The platform exposes folders as an index only, so this resource offers just
 * the collection surface (`query()`, `find()`, `all()`); there are no create or
 * write actions.
 *
 * ```php
 * SupportManager::documentFolders()->query()->filter('parent_id', 3)->get();
 * ```
 *
 * @extends \SupportManager\Sdk\Resources\Resource<DocumentFolder>
 */
final class DocumentFoldersResource extends Resource
{
    protected function path(): string
    {
        return 'documents/document-folders';
    }

    protected function hydrate(ResourceObject $object): DocumentFolder
    {
        return DocumentFolder::fromArray($object->flat());
    }
}
