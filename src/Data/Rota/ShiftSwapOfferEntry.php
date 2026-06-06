<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

/**
 * One shift offered as part of a swap offer.
 *
 * Mirrors the platform's `ShiftSwapOfferEntryResource`. `partial*` times are
 * set when only part of the offered shift is in scope; the underlying
 * {@see Shift} is exposed as `rotaEntry` when loaded.
 */
final readonly class ShiftSwapOfferEntry
{
    public function __construct(
        public ?string $partialStart,
        public ?string $partialEnd,
        public ?Shift $rotaEntry,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed>|null $rotaEntry */
        $rotaEntry = is_array($data['rota_entry'] ?? null) ? $data['rota_entry'] : null;

        return new self(
            partialStart: isset($data['partial_start']) ? (string) $data['partial_start'] : null,
            partialEnd: isset($data['partial_end']) ? (string) $data['partial_end'] : null,
            rotaEntry: $rotaEntry !== null ? Shift::fromArray(NestedResource::flatten($rotaEntry)) : null,
        );
    }
}
