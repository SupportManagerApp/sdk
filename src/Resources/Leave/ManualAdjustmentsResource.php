<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Leave;

use SupportManager\Sdk\Data\Leave\ManualAdjustment;
use SupportManager\Sdk\Data\Leave\ManualAdjustmentSummary;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Concerns\Listable;
use SupportManager\Sdk\Resources\Resource;

/**
 * Manual adjustments to a user's leave balance.
 *
 * Exposes the privileged index collection plus `create()`, `update()` and
 * `destroy()`; the platform does not implement a per-adjustment show endpoint.
 * `mine()` is open to every team member and lists their own adjustments.
 *
 * ```php
 * SupportManager::leaveAdjustments()->query()->filter('user_id', 3)->get();
 * SupportManager::leaveAdjustments()->mine(['year' => 2026]);
 * ```
 *
 * @extends Resource<ManualAdjustment>
 */
final class ManualAdjustmentsResource extends Resource
{
    /** @use Listable<ManualAdjustment> */
    use Listable;

    /**
     * The current user's own adjustments for the current leave year or the
     * given one, oldest first, with their totals. Not paginated.
     *
     * @param  array<string, mixed>  $query  Optional `year`.
     */
    public function mine(array $query = []): ManualAdjustmentSummary
    {
        $page = $this->paginated($this->get("{$this->path()}/mine", $query));

        return ManualAdjustmentSummary::fromArray($page->items(), $page->meta());
    }

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
     * Update a manual adjustment, returning the updated record. Every field
     * (`user_id`, `leave_year`, `hours_adjustment`, `sleeps_adjustment`,
     * `reason`) is required. The platform accepts PUT and PATCH alike here;
     * this sends PATCH.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string|int $id, array $data): ?ManualAdjustment
    {
        return $this->item($this->patch("{$this->path()}/{$id}", $data));
    }

    /**
     * Permanently delete a manual adjustment.
     */
    public function destroy(string|int $id): void
    {
        $this->throwForErrorResponse($this->delete("{$this->path()}/{$id}"));
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
