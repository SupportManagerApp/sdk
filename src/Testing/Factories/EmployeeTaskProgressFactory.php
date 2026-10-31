<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Testing\Factory;

/**
 * Fake employee task standings, the records behind the manager-only
 * `tasks/manager-inbox` and `tasks/team-progress` views.
 *
 * These are flat (`type()` null) `{user, percentage, incomplete_tasks,
 * pending_tasks}` entries. They are stored under {@see TaskFactory::EMPLOYEE_PATH}
 * (a store key only; the platform has no such endpoint) and served by
 * {@see TaskFactory}'s routes (the endpoints live under the `tasks` prefix), so
 * seeding this factory alongside `TaskFactory` populates both manager views.
 *
 * ```php
 * SupportManager::fake()->seed(
 *     TaskFactory::new()->count(2),
 *     EmployeeTaskProgressFactory::new()->count(3),
 * );
 * SupportManager::tasks()->managerInbox();
 * ```
 */
final class EmployeeTaskProgressFactory extends Factory
{
    public function path(): string
    {
        return TaskFactory::EMPLOYEE_PATH;
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        $task = [
            'key' => TaskFactory::key($index),
            'label' => "Task {$number}",
            'description' => "Do task {$number}",
            'icon' => 'check',
            'completed' => false,
            'completion_role' => 'manager',
            'completion_role_label' => 'Manager Only',
            'priority' => 10,
        ];

        return [
            'user' => ['id' => $number, 'name' => "Employee {$number}"],
            'percentage' => 50,
            'incomplete_tasks' => [$task],
            'pending_tasks' => [$task],
        ];
    }
}
