<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

/**
 * A manual adjustment to a user's leave balance for a given leave year —
 * hours and/or sleeping-nights, with a reason.
 *
 * Mirrors the platform's `ManualAdjustmentResource` attributes.
 */
final readonly class ManualAdjustment
{
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?int $userId,
        public ?string $userName,
        public ?int $leaveYear,
        public float $hoursAdjustment,
        public float $sleepsAdjustment,
        public ?string $reason,
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

        return new self(
            id: (string) ($data['id'] ?? ''),
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            userName: isset($user['name']) ? (string) $user['name'] : null,
            leaveYear: isset($data['leave_year']) ? (int) $data['leave_year'] : null,
            hoursAdjustment: (float) ($data['hours_adjustment'] ?? 0),
            sleepsAdjustment: (float) ($data['sleeps_adjustment'] ?? 0),
            reason: isset($data['reason']) ? (string) $data['reason'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}
