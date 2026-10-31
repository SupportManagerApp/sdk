<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

use SupportManager\Sdk\Enums\Rota\EntryType;

/**
 * A user's request to the managers for extra hours — an overtime shift that is
 * created only once a manager approves it on the web.
 *
 * Mirrors the platform's `ShiftRequestResource` attributes. `type` is the
 * requested {@see EntryType} (`Overtime Hours` or `Overtime Sleeping Night`);
 * `status` is an open list (`Pending`, `Approved`, `Rejected`, …). `rotaEntryId`
 * is the shift created on approval. `canDelete` comes from the platform's
 * `meta.can.delete` flag — whether `cancel()` is open to the caller — and reads
 * false when the response carried no meta.
 */
final readonly class ShiftRequest
{
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?int $userId,
        public ?string $start,
        public ?string $end,
        public ?EntryType $type,
        public ?string $typeLabel,
        public ?string $reason,
        public ?int $requestedPayEnhancementId,
        public ?string $status,
        public ?int $reviewedBy,
        public ?string $reviewedAt,
        public ?int $rotaEntryId,
        public ?string $createdAt,
        public ?string $updatedAt,
        public bool $canDelete,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $meta */
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

        /** @var array<string, mixed> $can */
        $can = is_array($meta['can'] ?? null) ? $meta['can'] : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            start: isset($data['start']) ? (string) $data['start'] : null,
            end: isset($data['end']) ? (string) $data['end'] : null,
            type: isset($data['type']) ? EntryType::tryFrom((string) $data['type']) : null,
            typeLabel: isset($data['type_label']) ? (string) $data['type_label'] : null,
            reason: isset($data['reason']) ? (string) $data['reason'] : null,
            requestedPayEnhancementId: isset($data['requested_pay_enhancement_id']) ? (int) $data['requested_pay_enhancement_id'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            reviewedBy: isset($data['reviewed_by']) ? (int) $data['reviewed_by'] : null,
            reviewedAt: isset($data['reviewed_at']) ? (string) $data['reviewed_at'] : null,
            rotaEntryId: isset($data['rota_entry_id']) ? (int) $data['rota_entry_id'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            canDelete: (bool) ($can['delete'] ?? false),
        );
    }
}
