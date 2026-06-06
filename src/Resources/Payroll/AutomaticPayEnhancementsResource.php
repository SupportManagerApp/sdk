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
 * (non-JSON:API) `data` payload, so this resource hand-rolls both. `show()`
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
        $response = $this->get('payroll/automatic-pay-enhancements');

        $this->throwForErrorResponse($response, 'payroll/automatic-pay-enhancements');

        $data = $response->json('data');

        return is_array($data) ? AutomaticPayEnhancementSettings::fromArray($data) : null;
    }

    /**
     * Update the settings, returning the saved configuration.
     *
     * @param  array<string, mixed>  $attributes  Any of the four enhancement id fields.
     */
    public function update(array $attributes): ?AutomaticPayEnhancementSettings
    {
        $response = $this->put('payroll/automatic-pay-enhancements', $attributes);

        $this->throwForErrorResponse($response, 'payroll/automatic-pay-enhancements');

        $data = $response->json('data');

        return is_array($data) ? AutomaticPayEnhancementSettings::fromArray($data) : null;
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
