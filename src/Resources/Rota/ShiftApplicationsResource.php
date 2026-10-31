<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Rota;

use SupportManager\Sdk\Data\Rota\ShiftApplication;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Concerns\Listable;
use SupportManager\Sdk\Resources\Resource;

/**
 * The authenticated user's applications for available shifts.
 *
 * Index-only collection (`query()`, `all()`) plus `destroy()`, which retracts
 * a pending application. The platform exposes no show route, so there is
 * no `find()`.
 *
 * ```php
 * SupportManager::shiftApplications()->query()->filter('status', 'Pending')->get();
 * SupportManager::shiftApplications()->destroy($applicationId);
 * ```
 *
 * @extends Resource<ShiftApplication>
 */
final class ShiftApplicationsResource extends Resource
{
    /** @use Listable<ShiftApplication> */
    use Listable;

    /**
     * Retract a pending shift application (`DELETE rota/shift-applications/{id}`).
     * The platform returns only a confirmation message, so nothing is hydrated.
     */
    public function destroy(string|int $id): void
    {
        $this->throwForErrorResponse($this->delete("{$this->path()}/{$id}"));
    }

    /**
     * @deprecated Use {@see self::destroy()}, the name every resource gives a DELETE of one record.
     */
    public function cancel(string|int $id): void
    {
        $this->deprecated(__FUNCTION__, 'destroy');

        $this->destroy($id);
    }

    /**
     * @deprecated Use {@see self::destroy()}, the name every resource gives a DELETE of one record.
     */
    public function remove(string|int $id): void
    {
        $this->deprecated(__FUNCTION__, 'destroy');

        $this->destroy($id);
    }

    protected function path(): string
    {
        return 'rota/shift-applications';
    }

    protected function hydrate(ResourceObject $object): ShiftApplication
    {
        return ShiftApplication::fromArray($object->flat());
    }
}
