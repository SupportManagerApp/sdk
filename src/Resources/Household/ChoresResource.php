<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Household;

use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Data\Household\Chore;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Concerns\Listable;
use SupportManager\Sdk\Resources\Resource;

/**
 * Household chores.
 *
 * The collection (`query()`, `all()`; there is no `find()`, as the platform
 * registers chores without a `show` route) plus writes — `create()`,
 * `update()`, `destroy()`, limited to admins and managers — and the lifecycle
 * actions any team member may use: `complete()` (records who did it and when),
 * `snooze()` (hides it until a given date), and their bulk forms
 * `completeMany()`/`completeGroup()` and `markNotNeeded()`/
 * `markGroupNotNeeded()`. The platform exposes no `show` route, so single
 * chores come from the collection.
 *
 * `filter('due', true)` returns the chores due now, exactly as the web chore
 * list shows them (snoozed and not-needed-today chores are left out);
 * `filter('due', false)` returns every other chore.
 *
 * ```php
 * SupportManager::chores()->query()->filter('due', true)->get();
 * SupportManager::chores()->complete(7);
 * SupportManager::chores()->completeGroup('Water plants');
 * SupportManager::chores()->markNotNeeded([7, 8]);
 * ```
 *
 * @extends Resource<Chore>
 */
final class ChoresResource extends Resource
{
    /** @use Listable<Chore> */
    use Listable;

    /**
     * Create a new chore.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ?Chore
    {
        return $this->item($this->post('household/chores', $data));
    }

    /**
     * Update an existing chore.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string|int $id, array $data): ?Chore
    {
        return $this->item($this->patch("household/chores/{$id}", $data));
    }

    /**
     * Delete a chore (`DELETE household/chores/{id}`).
     */
    public function destroy(string|int $id): void
    {
        $this->throwForErrorResponse(parent::delete("{$this->path()}/{$id}"));
    }

    /**
     * Overrides the base `delete()` request helper, and returns its response,
     * only so that existing callers of `->delete($id)` keep working.
     *
     * @deprecated Use {@see self::destroy()}, the name every resource gives a DELETE of one record.
     *
     * @param  array<string, mixed>  $data
     */
    public function delete(string|int $id, array $data = []): Response
    {
        $this->deprecated(__FUNCTION__, 'destroy');

        $response = parent::delete("{$this->path()}/{$id}", $data);

        $this->throwForErrorResponse($response);

        return $response;
    }

    /**
     * Mark a chore complete, returning it with the refreshed completion state.
     */
    public function complete(string|int $id): ?Chore
    {
        return $this->item($this->patch("household/chores/{$id}/complete"));
    }

    /**
     * Snooze a chore until the given date (`Y-m-d`, after today), returning the
     * updated chore. Any team member may snooze until tomorrow — marking it not
     * needed today — but only admins and managers may snooze beyond that;
     * anyone else gets a 403. Prefer {@see self::markNotNeeded()} for "not
     * needed today", which lets the server pick tomorrow in its own timezone.
     */
    public function snooze(string|int $id, string $snoozedUntil): ?Chore
    {
        return $this->item($this->patch("household/chores/{$id}/snooze", [
            'snoozed_until' => $snoozedUntil,
        ]));
    }

    /**
     * Mark several chores complete at once, by the authenticated user, returning
     * them refreshed. If any chore is refused nothing changes and the platform
     * answers 403; an id outside the team is a 422 on `chore_ids.*`.
     *
     * @param  list<string|int>  $ids
     * @return list<Chore>
     */
    public function completeMany(array $ids): array
    {
        return $this->collection($this->post('household/chores/complete', ['chore_ids' => self::ids($ids)]));
    }

    /**
     * Mark every chore in a group complete — those whose name matches once
     * trimmed and lower-cased (see {@see Chore::$normalizedName}), as the web
     * app's "Complete all instances" does. A group with no chores is a 422 on
     * `group`.
     *
     * @return list<Chore>
     */
    public function completeGroup(string $group): array
    {
        return $this->collection($this->post('household/chores/complete', ['group' => $group]));
    }

    /**
     * Mark several chores as not needed today — snoozed until tomorrow, with the
     * authenticated user recorded in `snoozed_by` — returning them refreshed.
     * Fails atomically like {@see self::completeMany()}.
     *
     * @param  list<string|int>  $ids
     * @return list<Chore>
     */
    public function markNotNeeded(array $ids): array
    {
        return $this->collection($this->post('household/chores/not-needed', ['chore_ids' => self::ids($ids)]));
    }

    /**
     * Mark every chore in a group as not needed today, selected as for
     * {@see self::completeGroup()}.
     *
     * @return list<Chore>
     */
    public function markGroupNotNeeded(string $group): array
    {
        return $this->collection($this->post('household/chores/not-needed', ['group' => $group]));
    }

    protected function path(): string
    {
        return 'household/chores';
    }

    protected function hydrate(ResourceObject $object): Chore
    {
        return Chore::fromArray($object->flat());
    }

    /**
     * Chore ids as the integers the platform validates `chore_ids.*` against.
     *
     * @param  list<string|int>  $ids
     * @return list<int>
     */
    private static function ids(array $ids): array
    {
        return array_values(array_map(intval(...), $ids));
    }
}
