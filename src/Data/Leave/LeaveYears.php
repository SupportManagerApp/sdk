<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Leave;

/**
 * The leave years the authenticated user can browse — every year from the one
 * their employment started in ({@see $minimum}) to the later of the current
 * leave year and the latest year they have an entitlement in ({@see $maximum}).
 *
 * Mirrors the platform's `GET leave/balance/years` payload, which drives the
 * leave-summary widget's year picker. Years locked at year end stay in
 * {@see $years} and are also listed in {@see $locked}, since leave can no longer
 * be booked in them. `labels` maps each year to its display label,
 * accommodating teams whose leave year does not start in January (e.g.
 * `2026 => '2026/27'`).
 */
final readonly class LeaveYears
{
    /**
     * @param  list<int>  $years
     * @param  list<int>  $locked
     * @param  array<int, string>  $labels
     */
    public function __construct(
        public array $years,
        public ?int $minimum,
        public ?int $maximum,
        public int $current,
        public array $locked,
        public array $labels,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The response `data` block.
     */
    public static function fromArray(array $data): self
    {
        $labels = [];

        if (is_array($data['labels'] ?? null)) {
            foreach ($data['labels'] as $year => $label) {
                $labels[(int) $year] = (string) $label;
            }
        }

        return new self(
            years: self::years($data['years'] ?? null),
            minimum: isset($data['minimum']) ? (int) $data['minimum'] : null,
            maximum: isset($data['maximum']) ? (int) $data['maximum'] : null,
            current: (int) ($data['current'] ?? 0),
            locked: self::years($data['locked'] ?? null),
            labels: $labels,
        );
    }

    /**
     * Whether the given year has been locked at year end, so leave can no
     * longer be booked in it.
     */
    public function isLocked(int $year): bool
    {
        return in_array($year, $this->locked, true);
    }

    /**
     * @return list<int>
     */
    private static function years(mixed $value): array
    {
        return is_array($value) ? array_values(array_map(intval(...), $value)) : [];
    }
}
