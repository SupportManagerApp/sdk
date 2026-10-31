<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Rota;

use SupportManager\Sdk\Data\Rota\ShiftApplication;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * The authenticated user's applications for available shifts.
 *
 * Index-only collection (`query()`, `all()`) plus the ability to withdraw a
 * pending application. The platform exposes no show route, so `find()` is not
 * used here.
 *
 * ```php
 * SupportManager::shiftApplications()->query()->filter('status', 'Pending')->get();
 * SupportManager::shiftApplications()->cancel($applicationId);
 * ```
 *
 * @extends resource<ShiftApplication>
 */
final class ShiftApplicationsResource extends Resource
{
    /**
     * Withdraw a pending shift application. The platform returns only a
     * confirmation message, so nothing is hydrated.
     */
    public function cancel(string|int $id): void
    {
        $this->throwForErrorResponse($this->delete("{$this->path()}/{$id}"));
    }

    /**
     * Alias for {@see self::cancel()} — withdraw a pending application.
     */
    public function remove(string|int $id): void
    {
        $this->cancel($id);
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
