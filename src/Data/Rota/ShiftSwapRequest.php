<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

use SupportManager\Sdk\Enums\Rota\ShiftSwapStatus;

/**
 * A shift swap request — the container tracking the multi-step swap workflow.
 *
 * Mirrors the platform's `ShiftSwapRequestResource`. `status` is the typed
 * {@see ShiftSwapStatus}; `statusLabel` is the platform's display label. The
 * requester/target/reviewer are summarised as flat id/name fields, and the
 * shifts on each side are exposed as lists of {@see ShiftSwapEntry} when loaded.
 */
final readonly class ShiftSwapRequest
{
    /**
     * @param  list<ShiftSwapEntry>  $requesterEntries
     * @param  list<ShiftSwapEntry>  $respondentEntries
     * @param  list<ShiftSwapOffer>  $offers
     */
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?string $type,
        public ?ShiftSwapStatus $status,
        public ?string $statusLabel,
        public ?string $expiresAt,
        public bool $isExpired,
        public ?string $notes,
        public ?int $requesterId,
        public ?string $requesterName,
        public ?int $targetUserId,
        public ?string $targetUserName,
        public ?int $reviewerId,
        public ?string $reviewerName,
        public ?string $reviewedAt,
        public array $requesterEntries,
        public array $respondentEntries,
        public float $requesterTotalHours,
        public float $respondentTotalHours,
        public bool $hoursAreEqual,
        public bool $requiresManagerApproval,
        public array $offers,
        public ?int $offersCount,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id?: mixed, name?: mixed} $requester */
        $requester = is_array($data['requester'] ?? null) ? $data['requester'] : [];

        /** @var array{id?: mixed, name?: mixed} $targetUser */
        $targetUser = is_array($data['target_user'] ?? null) ? $data['target_user'] : [];

        /** @var array{id?: mixed, name?: mixed} $reviewer */
        $reviewer = is_array($data['reviewer'] ?? null) ? $data['reviewer'] : [];

        /** @var array<array-key, mixed> $requesterEntries */
        $requesterEntries = is_array($data['requester_entries'] ?? null) ? $data['requester_entries'] : [];

        /** @var array<array-key, mixed> $respondentEntries */
        $respondentEntries = is_array($data['respondent_entries'] ?? null) ? $data['respondent_entries'] : [];

        /** @var array<array-key, mixed> $offers */
        $offers = is_array($data['offers'] ?? null) ? $data['offers'] : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            type: isset($data['type']) ? (string) $data['type'] : null,
            status: isset($data['status']) ? ShiftSwapStatus::tryFrom((string) $data['status']) : null,
            statusLabel: isset($data['status_label']) ? (string) $data['status_label'] : null,
            expiresAt: isset($data['expires_at']) ? (string) $data['expires_at'] : null,
            isExpired: (bool) ($data['is_expired'] ?? false),
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
            requesterId: isset($requester['id']) ? (int) $requester['id'] : null,
            requesterName: isset($requester['name']) ? (string) $requester['name'] : null,
            targetUserId: isset($targetUser['id']) ? (int) $targetUser['id'] : null,
            targetUserName: isset($targetUser['name']) ? (string) $targetUser['name'] : null,
            reviewerId: isset($reviewer['id']) ? (int) $reviewer['id'] : null,
            reviewerName: isset($reviewer['name']) ? (string) $reviewer['name'] : null,
            reviewedAt: isset($data['reviewed_at']) ? (string) $data['reviewed_at'] : null,
            requesterEntries: array_map(
                ShiftSwapEntry::fromArray(...),
                NestedResource::flattenMany($requesterEntries),
            ),
            respondentEntries: array_map(
                ShiftSwapEntry::fromArray(...),
                NestedResource::flattenMany($respondentEntries),
            ),
            requesterTotalHours: (float) ($data['requester_total_hours'] ?? 0),
            respondentTotalHours: (float) ($data['respondent_total_hours'] ?? 0),
            hoursAreEqual: (bool) ($data['hours_are_equal'] ?? false),
            requiresManagerApproval: (bool) ($data['requires_manager_approval'] ?? false),
            offers: array_map(
                ShiftSwapOffer::fromArray(...),
                NestedResource::flattenMany($offers),
            ),
            offersCount: isset($data['offers_count']) ? (int) $data['offers_count'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}
