<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Compliance;

use SupportManager\Sdk\Data\Compliance\DriversLicenceCheck;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Driving licence verification records.
 *
 * Read-only: the collection (`query()`, `find()`, `all()`) plus single-record
 * lookups. The platform exposes `index` and `show` only — there are no write
 * actions. Filter by `user_id`.
 *
 * ```php
 * SupportManager::driversLicenceChecks()->query()->filter('user_id', 7)->get();
 * SupportManager::driversLicenceChecks()->find(42);
 * ```
 *
 * @extends \SupportManager\Sdk\Resources\Resource<DriversLicenceCheck>
 */
final class DriversLicenceChecksResource extends Resource
{
    protected function path(): string
    {
        return 'compliance/drivers-licence-checks';
    }

    protected function hydrate(ResourceObject $object): DriversLicenceCheck
    {
        return DriversLicenceCheck::fromArray($object->flat());
    }
}
