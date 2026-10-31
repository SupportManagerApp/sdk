<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\Fake;
use SupportManager\Sdk\Testing\FakeRoute;
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
 * SupportManager::tasks()->all()->total();         // 3
 * SupportManager::tasks()->complete('task_1');
 * ```
 */
final class TaskFactory extends Factory implements ProvidesFakeRoutes
{
    public const EMPLOYEE_PATH = 'tasks/employee-progress';

    public function path(): string
    {
        return 'tasks';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'key' => "task_{$number}",
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
            FakeRoute::get('tasks/count', static function (RecordStore $store): mixed {
                $tasks = $store->all('tasks');
                $mine = self::pending($tasks, 'self');
                $inbox = self::pending($tasks, 'manager');

                return self::data([
                    'count' => $mine + $inbox,
                    'my_tasks' => $mine,
                    'manager_inbox' => $inbox,
                ]);
            }),

            FakeRoute::get('tasks/progress', static function (RecordStore $store): mixed {
                $tasks = $store->all('tasks');
                $total = count($tasks);
                $done = count(array_filter($tasks, static fn (array $t): bool => (bool) ($t['completed'] ?? false)));
                $percentage = $total === 0 ? 100 : (int) round($done / $total * 100);

                return self::data([
                    'percentage' => $percentage,
                    'completed' => $done,
                    'total' => $total,
                ]);
            }),

            FakeRoute::get('tasks/manager-inbox', static fn (RecordStore $store): mixed => self::list(
                $store->all(self::EMPLOYEE_PATH),
            )),

            FakeRoute::get('tasks/team-progress', static fn (RecordStore $store): mixed => self::list(
                $store->all(self::EMPLOYEE_PATH),
            )),

            FakeRoute::post('tasks/{id}/complete', static function (RecordStore $store, array $matches): mixed {
                $key = $matches[1];

                foreach ($store->all('tasks') as $index => $task) {
                    if (($task['key'] ?? null) === $key) {
                        $records = $store->all('tasks');
                        $records[$index] = [...$task, 'completed' => true];
                        $store->put('tasks', $records);

                        return Http::response(['message' => 'ok', 'data' => $records[$index]]);
                    }
                }

                return Http::response(['message' => 'Task not found.'], 404);
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

                return self::list(array_values($tasks), ['team_id' => 42]);
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

    /**
     * A plain `{data: {...}}` body for the scalar summary endpoints.
     *
     * @param  array<string, mixed>  $data
     */
    private static function data(array $data): mixed
    {
        return Http::response(['data' => $data]);
    }

    /**
     * A plain `{data: [...], meta}` body for the list endpoints.
     *
     * @param  list<array<string, mixed>>  $records
     * @param  array<string, mixed>  $meta
     */
    private static function list(array $records, array $meta = []): mixed
    {
        return Http::response(['data' => array_values($records), 'meta' => $meta]);
    }
}
