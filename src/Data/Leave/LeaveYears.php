<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

/**
 * The leave years selectable for the authenticated user — the current leave
 * year plus the next, excluding any locked at year end.
 *
 * Mirrors the platform's `GET leave/balance/years` payload, which drives the
 * leave-summary widget's year picker. `labels` maps each year to its display
 * label, accommodating teams whose leave year does not start in January (e.g.
 * `2026 => '2026/27'`).
 */
final readonly class LeaveYears
{
    /**
     * @param  list<int>  $years
     * @param  array<int, string>  $labels
     */
    public function __construct(
        public array $years,
        public int $current,
        public array $labels,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The response `data` block.
     */
    public static function fromArray(array $data): self
    {
        $years = is_array($data['years'] ?? null)
            ? array_values(array_map(intval(...), $data['years']))
            : [];

        $labels = [];

        if (is_array($data['labels'] ?? null)) {
            foreach ($data['labels'] as $year => $label) {
                $labels[(int) $year] = (string) $label;
            }
        }

        return new self(
            years: $years,
            current: (int) ($data['current'] ?? 0),
            labels: $labels,
        );
    }
}
