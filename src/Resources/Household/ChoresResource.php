<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Household;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Data\Household\Chore;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Household chores.
 *
 * The collection (`query()`, `find()`, `all()`) plus writes — `create()`,
 * `update()`, `delete()` — and the two lifecycle actions: `complete()` (records
 * who did it and when) and `snooze()` (hides it until a given date). The
 * platform exposes no `show` route, so single chores come from the collection.
 *
 * ```php
 * SupportManager::chores()->query()->filter('overdue', true)->get();
 * SupportManager::chores()->complete(7);
 * SupportManager::chores()->snooze(7, '2026-06-20');
 * ```
 *
 * @extends resource<Chore>
 */
final class ChoresResource extends Resource
{
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
     * Delete a chore. The base helper's signature is preserved for LSP, but
     * callers pass just the id: `->delete(5)`.
     *
     * @param  array<string, mixed>  $data
     */
    public function delete(string|int $id, array $data = []): Response
    {
        $uri = "household/chores/{$id}";

        $response = $this->tenant(fn (PendingRequest $request) => $request->delete($uri, $data));

        $this->throwForErrorResponse($response, $uri);

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
     * Snooze a chore until the given date (`Y-m-d`), returning the updated chore.
     */
    public function snooze(string|int $id, string $snoozedUntil): ?Chore
    {
        return $this->item($this->patch("household/chores/{$id}/snooze", [
            'snoozed_until' => $snoozedUntil,
        ]));
    }

    protected function path(): string
    {
        return 'household/chores';
    }

    protected function hydrate(ResourceObject $object): Chore
    {
        return Chore::fromArray($object->flat());
    }
}
