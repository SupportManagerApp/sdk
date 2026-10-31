<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Documents;

use SupportManager\Sdk\Data\Documents\DocumentFolder;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Concerns\Findable;
use SupportManager\Sdk\Resources\Concerns\Listable;
use SupportManager\Sdk\Resources\Resource;

/**
 * The team's document folders (read-only).
 *
 * The collection surface (`query()`, `find()`, `all()`); there are no create or
 * write actions. `find()` hits the platform's single-folder route, which also
 * returns the folder's `ancestors` for breadcrumbs (and 403s, rather than
 * 404s, for a folder the user may not browse). Filter by `scope` (`personal` /
 * `team`), `user_id` and `parent_id`; sort by `name` / `created_at`.
 *
 * ```php
 * SupportManager::documentFolders()->query()->filter('parent_id', 3)->get();
 * SupportManager::documentFolders()->query()->filter('scope', 'personal')->get();
 * SupportManager::documentFolders()->find(7)->ancestors; // [['id' => 1, 'name' => 'HR'], …]
 * ```
 *
 * @extends Resource<DocumentFolder>
 */
final class DocumentFoldersResource extends Resource
{
    /** @use Findable<DocumentFolder> */
    use Findable;

    /** @use Listable<DocumentFolder> */
    use Listable;

    protected function path(): string
    {
        return 'documents/document-folders';
    }

    protected function hydrate(ResourceObject $object): DocumentFolder
    {
        return DocumentFolder::fromArray($object->flat());
    }
}
