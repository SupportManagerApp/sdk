<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Hydrator;

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
        #[From('user.id')]
        public ?int $userId,
        #[From('user.name')]
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
        /** @var array<string, mixed>|null $rotaEntry */
        $rotaEntry = is_array($data['rota_entry'] ?? null) ? $data['rota_entry'] : null;

        return Hydrator::hydrate(self::class, $data, [
            'rotaEntry' => $rotaEntry !== null ? Shift::fromArray(NestedResource::flatten($rotaEntry)) : null,
        ]);
    }
}
