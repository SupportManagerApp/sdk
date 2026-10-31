<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Tasks;

use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * The dashboard task badge counts: the user's own outstanding tasks, the
 * manager inbox (0 for non-privileged users), and their sum.
 *
 * Mirrors the platform's `GET tasks/count` payload.
 */
final readonly class TaskCount
{
    public function __construct(
        public int $count,
        public int $myTasks,
        public int $managerInbox,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The response `data` member.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
