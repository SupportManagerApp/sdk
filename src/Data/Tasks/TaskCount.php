<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Tasks;

/**
 * The dashboard task badge counts: the user's own outstanding tasks, the
 * manager inbox (0 for non-privileged users), and their sum.
 *
 * Mirrors the platform's `GET tasks/tasks/count` payload.
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
        return new self(
            count: (int) ($data['count'] ?? 0),
            myTasks: (int) ($data['my_tasks'] ?? 0),
            managerInbox: (int) ($data['manager_inbox'] ?? 0),
        );
    }
}
