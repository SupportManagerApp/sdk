<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Compliance;

use SupportManager\Sdk\Data\Compliance\DBSCheck;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * DBS (Disclosure and Barring Service) check records.
 *
 * Read-only: the collection (`query()`, `find()`, `all()`) plus single-record
 * lookups. The platform exposes `index` and `show` only — there are no write
 * actions. Filter by `user_id` and sort by `date_of_issue` / `created_at`.
 *
 * ```php
 * SupportManager::dbsChecks()->query()->filter('user_id', 7)->sort('-date_of_issue')->get();
 * SupportManager::dbsChecks()->find(42);
 * ```
 *
 * @extends \SupportManager\Sdk\Resources\Resource<DBSCheck>
 */
final class DBSChecksResource extends Resource
{
    protected function path(): string
    {
        return 'compliance/dbs-checks';
    }

    protected function hydrate(ResourceObject $object): DBSCheck
    {
        return DBSCheck::fromArray($object->flat());
    }
}
