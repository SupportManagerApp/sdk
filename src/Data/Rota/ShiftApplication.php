<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

/**
 * A user's application for an available shift.
 *
 * Mirrors the platform's `ShiftApplicationResource` attributes. The applied-for
 * shift is exposed as the nested {@see Shift} `rotaEntry` when loaded; the
 * applicant is summarised as flat `userId`/`userName` fields. `isProvisional`
 * (an admin's pencilled-in allocation) is sent to privileged users only and is
 * null for everybody else, who see such an application as `Pending`.
 */
final readonly class ShiftApplication
{
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?string $status,
        public ?string $startTime,
        public ?string $endTime,
        public ?int $userId,
        public ?string $userName,
        public ?Shift $rotaEntry,
        public ?int $reviewedBy,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?bool $isProvisional = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id?: mixed, name?: mixed} $user */
        $user = is_array($data['user'] ?? null) ? $data['user'] : [];

        /** @var array<string, mixed>|null $rotaEntry */
        $rotaEntry = is_array($data['rota_entry'] ?? null) ? $data['rota_entry'] : null;

        return new self(
            id: (string) ($data['id'] ?? ''),
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            startTime: isset($data['start_time']) ? (string) $data['start_time'] : null,
            endTime: isset($data['end_time']) ? (string) $data['end_time'] : null,
            userId: isset($user['id']) ? (int) $user['id'] : null,
            userName: isset($user['name']) ? (string) $user['name'] : null,
            rotaEntry: $rotaEntry !== null ? Shift::fromArray(NestedResource::flatten($rotaEntry)) : null,
            reviewedBy: isset($data['reviewed_by']) ? (int) $data['reviewed_by'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            isProvisional: isset($data['is_provisional']) ? (bool) $data['is_provisional'] : null,
        );
    }
}
