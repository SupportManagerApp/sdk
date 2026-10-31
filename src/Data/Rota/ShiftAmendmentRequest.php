<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;
use SupportManager\Sdk\Data\Hydration\Hydrator;

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
        #[ListOf('string')]
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
        return Hydrator::hydrate(self::class, $data);
    }
}
