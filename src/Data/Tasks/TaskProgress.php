<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Tasks;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * The authenticated user's task completion progress, mirroring the platform's
 * `GET tasks/progress` payload. `percentage` is 100 when there are no
 * tasks.
 */
final readonly class TaskProgress
{
    public function __construct(
        public int $percentage,
        public int $completed,
        public int $total,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The response `data` member.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
