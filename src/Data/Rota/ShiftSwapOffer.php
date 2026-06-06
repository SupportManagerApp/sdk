<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

/**
 * An offer made against an open shift swap request.
 *
 * Mirrors the platform's `ShiftSwapOfferResource`. The offering user is
 * summarised as flat `userId`/`userName` fields; the offered shifts are the
 * list of {@see ShiftSwapOfferEntry} `entries` when loaded.
 */
final readonly class ShiftSwapOffer
{
    /**
     * @param  list<ShiftSwapOfferEntry>  $entries
     */
    public function __construct(
        public string $id,
        public ?string $status,
        public ?string $notes,
        public ?int $userId,
        public ?string $userName,
        public array $entries,
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

        /** @var array<array-key, mixed> $entries */
        $entries = is_array($data['entries'] ?? null) ? $data['entries'] : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            status: isset($data['status']) ? (string) $data['status'] : null,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
            userId: isset($user['id']) ? (int) $user['id'] : null,
            userName: isset($user['name']) ? (string) $user['name'] : null,
            entries: array_map(
                ShiftSwapOfferEntry::fromArray(...),
                NestedResource::flattenMany($entries),
            ),
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}
