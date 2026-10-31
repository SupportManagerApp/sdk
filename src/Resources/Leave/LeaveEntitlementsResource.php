<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Leave;

use SupportManager\Sdk\Data\Leave\LeaveEntitlement;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Concerns\Listable;
use SupportManager\Sdk\Resources\Resource;

/**
 * Annual leave entitlements (read-only).
 *
 * The platform exposes only an index endpoint here, so this resource offers
 * `query()`/`all()` and nothing that writes. Administrative create/update/
 * delete of entitlements lives on {@see AdminLeaveResource}.
 *
 * ```php
 * SupportManager::leaveEntitlements()->query()->filter('user_id', 3)->get();
 * ```
 *
 * @extends Resource<LeaveEntitlement>
 */
final class LeaveEntitlementsResource extends Resource
{
    /** @use Listable<LeaveEntitlement> */
    use Listable;

    protected function path(): string
    {
        return 'leave/entitlements';
    }

    protected function hydrate(ResourceObject $object): LeaveEntitlement
    {
        return LeaveEntitlement::fromArray($object->flat());
    }
}
