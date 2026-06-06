<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Fixtures\CarJourneys;

/**
 * Throwaway DTO used only to exercise the SDK's resource/query/pagination seams
 * against a realistic JSON:API payload. It mirrors the platform's
 * CarJourneyResource attributes but is intentionally not shipped as real domain
 * code — the domain DTOs are out of scope for now.
 */
final readonly class CarJourney
{
    public function __construct(
        public string $id,
        public string $driverName,
        public string $tripStart,
        public ?string $tripEnd,
        public bool $isOngoing,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            driverName: (string) ($data['driver_name'] ?? ''),
            tripStart: (string) ($data['trip_start'] ?? ''),
            tripEnd: $data['trip_end'] ?? null,
            isOngoing: (bool) ($data['is_ongoing'] ?? false),
        );
    }
}
