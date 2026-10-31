<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * One possible match for an ambiguous quick-add `assignee_name` — what a
 * client offers when it has to ask "which Sarah?".
 *
 * Mirrors an entry of the platform's `assignee_candidates`; `score` is the
 * match strength from 0.0 to 1.0.
 */
final readonly class QuickAddAssigneeCandidate
{
    public function __construct(
        public int $id,
        public string $name,
        public float $score,
    ) {}

    /**
     * @param  array<string, mixed>  $data  One `assignee_candidates` entry.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
