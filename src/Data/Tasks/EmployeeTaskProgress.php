<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Tasks;

use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * One employee's task standing, mirroring the platform's
 * `EmployeeTaskProgressResource`.
 *
 * The shape varies by endpoint: team-progress carries `percentage` and the
 * employee's `incompleteTasks`; the manager inbox carries the `pendingTasks`
 * awaiting a manager. Fields not present for a given endpoint are null/empty.
 */
final readonly class EmployeeTaskProgress
{
    /**
     * @param  list<Task>  $incompleteTasks
     * @param  list<Task>  $pendingTasks
     */
    public function __construct(
        #[From('user.id')]
        public int $userId,
        #[From('user.name')]
        public string $userName,
        public ?int $percentage,
        #[ListOf(Task::class)]
        public array $incompleteTasks,
        #[ListOf(Task::class)]
        public array $pendingTasks,
    ) {}

    /**
     * @param  array<string, mixed>  $data  An entry from the response `data`.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
