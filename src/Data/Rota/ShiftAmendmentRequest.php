<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

/**
 * A user's request to the managers to change one of their shifts — its times,
 * pay enhancement or tags — reviewed by a manager on the web.
 *
 * Mirrors the platform's `ShiftAmendmentRequestResource` attributes.
 * `proposedStart`/`proposedEnd` are the requested times, placed on the day the
 * shift starts; `status` is an open list (`Pending`, `Approved`, `Rejected`, …).
 */
final readonly class ShiftAmendmentRequest
{
    /**
     * @param  list<string>  $requestedTags
     */
    public function __construct(
        public string $id,
        public ?int $teamId,
        public ?int $userId,
        public ?int $rotaEntryId,
        public ?string $proposedStart,
        public ?string $proposedEnd,
        public ?int $requestedPayEnhancementId,
        public array $requestedTags,
        public ?string $reason,
        public ?string $status,
        public ?int $reviewedBy,
        public ?string $reviewedAt,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        $tags = [];

        foreach (is_array($data['requested_tags'] ?? null) ? $data['requested_tags'] : [] as $tag) {
            if (is_string($tag)) {
                $tags[] = $tag;
            }
        }

        return new self(
            id: (string) ($data['id'] ?? ''),
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            rotaEntryId: isset($data['rota_entry_id']) ? (int) $data['rota_entry_id'] : null,
            proposedStart: isset($data['proposed_start']) ? (string) $data['proposed_start'] : null,
            proposedEnd: isset($data['proposed_end']) ? (string) $data['proposed_end'] : null,
            requestedPayEnhancementId: isset($data['requested_pay_enhancement_id']) ? (int) $data['requested_pay_enhancement_id'] : null,
            requestedTags: $tags,
            reason: isset($data['reason']) ? (string) $data['reason'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            reviewedBy: isset($data['reviewed_by']) ? (int) $data['reviewed_by'] : null,
            reviewedAt: isset($data['reviewed_at']) ? (string) $data['reviewed_at'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}
