<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\Fake;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake dashboard tasks.
 *
 * These endpoints are NOT JSON:API — every one returns a plain `{data, meta}`
 * body (a flat list under `data`, never wrapped resource objects), so `type()`
 * stays null and the whole module is served by explicit routes rather than
 * generic CRUD. Tasks are keyed by `key`, not an auto-increment `id`.
 *
 * `all()` lists seeded tasks (honouring the `completed`/`completion_role`
 * filters); `count()` and `progress()` are derived from those tasks; `complete()`
 * flips a task's `completed` flag by its key. The manager views
 * (`manager-inbox`/`team-progress`) read employee records seeded via
 * {@see EmployeeTaskProgressFactory}.
 *
 * ```php
 * SupportManager::fake()->seed(TaskFactory::new()->count(3));
 * count(SupportManager::tasks()->all()->items()); // 3
 * SupportManager::tasks()->complete('task_a');
 * ```
 */
final class TaskFactory extends Factory implements ProvidesFakeRoutes
{
    public const EMPLOYEE_PATH = 'tasks/employee-progress';

    /**
     * The seeded task key for an index: `task_a`, `task_b`, …. Platform task
     * keys only contain `[a-z._]` (the `tasks/{taskKey}/complete` route
     * constraint), so the key carries no digits.
     */
    public static function key(int $index): string
    {
        $key = '';

        do {
            $key = chr(97 + $index % 26).$key;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return "task_{$key}";
    }

    public function path(): string
    {
        return 'tasks';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'key' => self::key($index),
            'label' => "Task {$number}",
            'description' => "Do task {$number}",
            'icon' => 'check',
            'completed' => false,
            'completion_role' => 'self',
            'completion_role_label' => 'Employee Only',
            'priority' => 10,
        ];
    }

    /**
     * Seed an already-completed task.
     */
    public function completed(): static
    {
        return $this->state(['completed' => true]);
    }

    /**
     * Seed a manager-only task.
     */
    public function managerOnly(): static
    {
        return $this->state([
            'completion_role' => 'manager',
            'completion_role_label' => 'Manager Only',
        ]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::flat('tasks/count', static function (RecordStore $store): array {
                $tasks = $store->all('tasks');
                $mine = self::pending($tasks, 'self');
                $inbox = self::pending($tasks, 'manager');

                return [
                    'count' => $mine + $inbox,
                    'my_tasks' => $mine,
                    'manager_inbox' => $inbox,
                ];
            }),

            FakeRoute::flat('tasks/progress', static function (RecordStore $store): array {
                $tasks = $store->all('tasks');
                $total = count($tasks);
                $done = count(array_filter($tasks, static fn (array $t): bool => (bool) ($t['completed'] ?? false)));

                return [
                    'percentage' => $total === 0 ? 100 : (int) round($done / $total * 100),
                    'completed' => $done,
                    'total' => $total,
                ];
            }),

            // EmployeeTaskProgressResource emits {user, pending_tasks} for the
            // inbox and {user, percentage, incomplete_tasks} for team progress.
            FakeRoute::get('tasks/manager-inbox', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $rows = array_map(
                    static fn (array $row): array => ['user' => $row['user'] ?? null, 'pending_tasks' => $row['pending_tasks'] ?? []],
                    $store->all(self::EMPLOYEE_PATH),
                );

                return Http::response(Json::flat($rows, [
                    'team_id' => $fake->teamId(),
                    'total_pending' => array_sum(array_map(static fn (array $row): int => count($row['pending_tasks']), $rows)),
                ]));
            }),

            FakeRoute::get('tasks/team-progress', static fn (RecordStore $store, array $matches, Request $request, Fake $fake): mixed => Http::response(Json::flat(
                array_map(
                    static fn (array $row): array => [
                        'user' => $row['user'] ?? null,
                        'percentage' => $row['percentage'] ?? 0,
                        'incomplete_tasks' => $row['incomplete_tasks'] ?? [],
                    ],
                    $store->all(self::EMPLOYEE_PATH),
                ),
                ['team_id' => $fake->teamId()],
            ))),

            FakeRoute::post('tasks/{id}/complete', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $key = $matches[1];

                foreach ($store->all('tasks') as $index => $task) {
                    if (($task['key'] ?? null) === $key) {
                        $records = $store->all('tasks');
                        $records[$index] = [...$task, 'completed' => true];
                        $store->put('tasks', $records);

                        return Http::response(['message' => 'Task cache cleared successfully.', ...Json::flat($records[$index], ['team_id' => $fake->teamId()])]);
                    }
                }

                return Json::notFound('Task not found.');
            }),

            // Index: plain {data, meta} with the completed/completion_role filters.
            FakeRoute::get('tasks', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $query = $fake->query($request);
                $tasks = $store->all('tasks');

                if (isset($query['completed'])) {
                    $completed = $query['completed'] === 'true';
                    $tasks = array_filter($tasks, static fn (array $t): bool => (bool) ($t['completed'] ?? false) === $completed);
                }

                if (isset($query['completion_role']) && is_string($query['completion_role'])) {
                    $role = $query['completion_role'];
                    $tasks = array_filter($tasks, static fn (array $t): bool => ($t['completion_role'] ?? null) === $role);
                }

                return Http::response(Json::flat(array_values($tasks), ['team_id' => $fake->teamId()]));
            }),
        ];
    }

    /**
     * The number of outstanding tasks for a completion role.
     *
     * @param  list<array<string, mixed>>  $tasks
     */
    private static function pending(array $tasks, string $role): int
    {
        return count(array_filter(
            $tasks,
            static fn (array $t): bool => ($t['completion_role'] ?? 'self') === $role && ! ($t['completed'] ?? false),
        ));
    }
}
