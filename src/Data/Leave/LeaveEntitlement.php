<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

/**
 * An annual leave entitlement linked to a working pattern — the hours and
 * sleeping-nights allocated over an effective date range.
 *
 * Mirrors the platform's `LeaveEntitlementResource` attributes.
 */
final readonly class LeaveEntitlement
{
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?int $userId,
        public ?string $userName,
        public ?int $workingPatternId,
        public ?string $workingPatternName,
        public ?string $effectiveFrom,
        public ?string $effectiveUntil,
        public float $hoursHoliday,
        public float $sleepingNightsHoliday,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id?: mixed, name?: mixed} $user */
        $user = is_array($data['user'] ?? null) ? $data['user'] : [];
        /** @var array{id?: mixed, name?: mixed} $pattern */
        $pattern = is_array($data['working_pattern'] ?? null) ? $data['working_pattern'] : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            userName: isset($user['name']) ? (string) $user['name'] : null,
            workingPatternId: isset($data['working_pattern_id']) ? (int) $data['working_pattern_id'] : null,
            workingPatternName: isset($pattern['name']) ? (string) $pattern['name'] : null,
            effectiveFrom: isset($data['effective_from']) ? (string) $data['effective_from'] : null,
            effectiveUntil: isset($data['effective_until']) ? (string) $data['effective_until'] : null,
            hoursHoliday: (float) ($data['hours_holiday'] ?? 0),
            sleepingNightsHoliday: (float) ($data['sleeping_nights_holiday'] ?? 0),
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}
