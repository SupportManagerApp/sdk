<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Tasks;

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
        public int $userId,
        public string $userName,
        public ?int $percentage,
        public array $incompleteTasks,
        public array $pendingTasks,
    ) {}

    /**
     * @param  array<string, mixed>  $data  An entry from the response `data`.
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id?: mixed, name?: mixed} $user */
        $user = is_array($data['user'] ?? null) ? $data['user'] : [];

        return new self(
            userId: (int) ($user['id'] ?? 0),
            userName: (string) ($user['name'] ?? ''),
            percentage: isset($data['percentage']) ? (int) $data['percentage'] : null,
            incompleteTasks: self::tasks($data['incomplete_tasks'] ?? []),
            pendingTasks: self::tasks($data['pending_tasks'] ?? []),
        );
    }

    /**
     * @return list<Task>
     */
    private static function tasks(mixed $tasks): array
    {
        if (! is_array($tasks)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $task): Task => Task::fromArray(is_array($task) ? $task : []),
            $tasks,
        ));
    }
}
