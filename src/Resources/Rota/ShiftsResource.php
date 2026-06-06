<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Rota;

use SupportManager\Sdk\Data\Rota\Shift;
use SupportManager\Sdk\Data\Rota\ShiftApplication;
use SupportManager\Sdk\Http\Document;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Published rota entries — the team's scheduled shifts and leave blocks.
 *
 * The collection (`query()`, `find()`, `all()`) plus the module's read feeds —
 * `today()`'s shifts and the unassigned `available()` shifts open for
 * application — and the `apply()` action a user uses to apply for one.
 *
 * ```php
 * SupportManager::shifts()->query()->filter('mode', 'available')->sort('start')->get();
 * SupportManager::shifts()->today();
 * SupportManager::shifts()->apply($shiftId);
 * ```
 *
 * @extends resource<Shift>
 */
final class ShiftsResource extends Resource
{
    /**
     * Today's published shifts for the team.
     *
     * @return list<Shift>
     */
    public function today(): array
    {
        return $this->collection($this->get('rota/shifts/today'));
    }

    /**
     * Unassigned, published shifts open for application.
     *
     * @return list<Shift>
     */
    public function available(): array
    {
        return $this->collection($this->get('rota/shifts/available'));
    }

    /**
     * Apply for an available shift. Returns the created application(s); the
     * platform supports split applications, so a list is returned.
     *
     * @param  array<string, mixed>  $payload  Optional split/partial application data.
     * @return list<ShiftApplication>
     */
    public function apply(string|int $id, array $payload = []): array
    {
        $response = $this->post("rota/shifts/{$id}/apply", $payload);

        $this->throwForErrorResponse($response, "rota/shifts/{$id}/apply");

        return array_map(
            static fn (ResourceObject $object): ShiftApplication => ShiftApplication::fromArray($object->flat()),
            Document::fromArray($response->json() ?? [])->resources(),
        );
    }

    protected function path(): string
    {
        return 'rota/shifts';
    }

    protected function hydrate(ResourceObject $object): Shift
    {
        return Shift::fromArray($object->flat());
    }
}
