<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Payroll;

/**
 * The team's automatic pay-enhancement settings — which {@see PayEnhancement}
 * is auto-applied for each recognised scenario. Mirrors the platform's
 * `AutomaticPayEnhancementResource`.
 *
 * Each field holds the id of the configured enhancement, or null when no
 * enhancement is mapped to that scenario. The platform returns this as a flat
 * (non-JSON:API) `data` payload.
 */
final readonly class AutomaticPayEnhancementSettings
{
    public function __construct(
        public ?int $bankHolidayEnhancementId,
        public ?int $christmasEnhancementId,
        public ?int $holidayCoverEnhancementId,
        public ?int $sicknessCoverEnhancementId,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flat (non-JSON:API) settings payload.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            bankHolidayEnhancementId: isset($data['bank_holiday_enhancement_id']) ? (int) $data['bank_holiday_enhancement_id'] : null,
            christmasEnhancementId: isset($data['christmas_enhancement_id']) ? (int) $data['christmas_enhancement_id'] : null,
            holidayCoverEnhancementId: isset($data['holiday_cover_enhancement_id']) ? (int) $data['holiday_cover_enhancement_id'] : null,
            sicknessCoverEnhancementId: isset($data['sickness_cover_enhancement_id']) ? (int) $data['sickness_cover_enhancement_id'] : null,
        );
    }
}
