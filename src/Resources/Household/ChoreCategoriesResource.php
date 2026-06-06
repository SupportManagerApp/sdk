<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Household;

use SupportManager\Sdk\Data\Household\ChoreCategory;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Chore categories — read-only.
 *
 * The platform exposes only an index route, so this resource supports `query()`
 * and `all()` (with the usual Spatie filters/sorts) but no writes or by-id
 * lookups.
 *
 * ```php
 * SupportManager::choreCategories()->query()->sort('name')->get();
 * ```
 *
 * @extends resource<ChoreCategory>
 */
final class ChoreCategoriesResource extends Resource
{
    protected function path(): string
    {
        return 'household/chore-categories';
    }

    protected function hydrate(ResourceObject $object): ChoreCategory
    {
        return ChoreCategory::fromArray($object->flat());
    }
}
