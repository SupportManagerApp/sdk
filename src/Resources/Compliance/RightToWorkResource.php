<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Compliance;

use SupportManager\Sdk\Data\Compliance\RightToWork;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Right-to-work document records.
 *
 * Read-only: the collection (`query()`, `find()`, `all()`) plus single-record
 * lookups. The platform exposes `index` and `show` only — there are no write
 * actions. Filter by `user_id`.
 *
 * ```php
 * SupportManager::rightToWork()->query()->filter('user_id', 7)->get();
 * SupportManager::rightToWork()->find(42);
 * ```
 *
 * @extends \SupportManager\Sdk\Resources\Resource<RightToWork>
 */
final class RightToWorkResource extends Resource
{
    protected function path(): string
    {
        return 'compliance/right-to-work';
    }

    protected function hydrate(ResourceObject $object): RightToWork
    {
        return RightToWork::fromArray($object->flat());
    }
}
