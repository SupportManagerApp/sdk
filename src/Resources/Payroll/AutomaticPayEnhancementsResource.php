<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Payroll;

use SupportManager\Sdk\Data\Payroll\AutomaticPayEnhancementSettings;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * The team's automatic pay-enhancement settings — a singleton mapping of
 * scenarios (bank holiday, Christmas, holiday/sickness cover) to enhancements.
 *
 * The platform exposes a singleton `show()` and `update()` returning a flat
 * (non-JSON:API) `data` payload, read with {@see Resource::data()}. `show()`
 * returns null when the team has not configured any settings yet.
 *
 * ```php
 * SupportManager::automaticPayEnhancements()->show();
 * SupportManager::automaticPayEnhancements()->update(['bank_holiday_enhancement_id' => 3]);
 * ```
 *
 * @extends resource<AutomaticPayEnhancementSettings>
 */
final class AutomaticPayEnhancementsResource extends Resource
{
    /**
     * The current settings, or null when none have been configured.
     */
    public function show(): ?AutomaticPayEnhancementSettings
    {
        return $this->data($this->get($this->path()), AutomaticPayEnhancementSettings::class);
    }

    /**
     * Update the settings, returning the saved configuration.
     *
     * @param  array<string, mixed>  $attributes  Any of the four enhancement id fields.
     */
    public function update(array $attributes): ?AutomaticPayEnhancementSettings
    {
        return $this->data($this->put($this->path(), $attributes), AutomaticPayEnhancementSettings::class);
    }

    protected function path(): string
    {
        return 'payroll/automatic-pay-enhancements';
    }

    protected function hydrate(ResourceObject $object): AutomaticPayEnhancementSettings
    {
        return AutomaticPayEnhancementSettings::fromArray($object->flat());
    }
}
