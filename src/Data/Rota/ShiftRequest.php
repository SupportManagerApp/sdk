<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

use SupportManager\Sdk\Data\Hydration\Attributes\Can;
use SupportManager\Sdk\Data\Hydration\Hydrator;
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
        #[Can('delete')]
        public bool $canDelete,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
