<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Leave;

use SupportManager\Sdk\Data\Leave\ManualAdjustment;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Manual adjustments to a user's leave balance (privileged).
 *
 * Exposes the index collection plus `create()` and `delete()`; the platform
 * does not implement a per-adjustment show endpoint.
 *
 * ```php
 * SupportManager::leaveAdjustments()->query()->filter('user_id', 3)->get();
 * ```
 *
 * @extends resource<ManualAdjustment>
 */
final class ManualAdjustmentsResource extends Resource
{
    /**
     * Record a new manual adjustment, returning the stored record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ?ManualAdjustment
    {
        return $this->item($this->post($this->path(), $data));
    }

    /**
     * Permanently delete a manual adjustment.
     */
    public function destroy(string|int $id): void
    {
        $this->throwForErrorResponse($this->delete("{$this->path()}/{$id}"), $this->path());
    }

    protected function path(): string
    {
        return 'leave/adjustments';
    }

    protected function hydrate(ResourceObject $object): ManualAdjustment
    {
        return ManualAdjustment::fromArray($object->flat());
    }
}
