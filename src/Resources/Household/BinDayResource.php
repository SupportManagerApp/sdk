<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Household;

use SupportManager\Sdk\Data\Household\BinCollectionSchedule;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * The team's bin collections from Dorset Council — a singleton, not a
 * collection, and the data behind the `household.bin-day` dashboard widget.
 *
 * The platform's `BinDayController` returns a flat `{data: {bins_due_tomorrow,
 * collections}, meta}` body, not JSON:API, so it is read with
 * {@see Resource::raw()}. The dates are cached on the platform for an hour.
 *
 * Bin day is part of the Household module. Like every household endpoint, the
 * platform 404s while the team has Household switched off, and 403s shift
 * workers outside the hour either side of their shift and former PAs; both
 * throw a `SupportManagerApiException`. Check
 * `dashboard()->capabilities()->usesModule(OptionalModule::Household)`, or
 * whether `dashboard()->layout()` places the `household.bin-day` widget,
 * before calling.
 *
 * ```php
 * $schedule = SupportManager::binDay()->show();
 * $schedule->hasBinsDueTomorrow();                        // whether to show the widget
 * $schedule->collectionOf(BinType::Recycling)?->nextCollectionOn;   // '2026-11-10'
 * ```
 *
 * @extends Resource<BinCollectionSchedule>
 */
final class BinDayResource extends Resource
{
    /**
     * Each bin's next collection, and which bins go out tomorrow. A bin whose
     * date the council cannot currently supply has a null `nextCollectionOn`.
     */
    public function show(): BinCollectionSchedule
    {
        return BinCollectionSchedule::fromArray($this->raw($this->get($this->path())));
    }

    protected function path(): string
    {
        return 'household/bin-day';
    }

    protected function hydrate(ResourceObject $object): BinCollectionSchedule
    {
        return BinCollectionSchedule::fromArray($object->flat());
    }
}
