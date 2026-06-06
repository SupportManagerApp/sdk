<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

/**
 * One shift on either side of a swap request.
 *
 * Mirrors the platform's `ShiftSwapEntryResource`. `side` is which party the
 * entry belongs to; `partial*` times are set when only part of the shift is
 * being swapped, while the `effective*` times/duration reflect the actual
 * portion in scope. The underlying {@see Shift} is exposed as `rotaEntry`.
 */
final readonly class ShiftSwapEntry
{
    public function __construct(
        public ?string $side,
        public bool $isPartial,
        public ?string $partialStart,
        public ?string $partialEnd,
        public ?string $effectiveStart,
        public ?string $effectiveEnd,
        public float $effectiveDuration,
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
            side: isset($data['side']) ? (string) $data['side'] : null,
            isPartial: (bool) ($data['is_partial'] ?? false),
            partialStart: isset($data['partial_start']) ? (string) $data['partial_start'] : null,
            partialEnd: isset($data['partial_end']) ? (string) $data['partial_end'] : null,
            effectiveStart: isset($data['effective_start']) ? (string) $data['effective_start'] : null,
            effectiveEnd: isset($data['effective_end']) ? (string) $data['effective_end'] : null,
            effectiveDuration: (float) ($data['effective_duration'] ?? 0),
            rotaEntry: $rotaEntry !== null ? Shift::fromArray(NestedResource::flatten($rotaEntry)) : null,
        );
    }
}
