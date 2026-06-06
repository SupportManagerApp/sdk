<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\Tasks\EmployeeTaskProgress;
use SupportManager\Sdk\Data\Tasks\Task;
use SupportManager\Sdk\Data\Tasks\TaskCount;
use SupportManager\Sdk\Data\Tasks\TaskProgress;
use SupportManager\Sdk\Enums\Tasks\TaskCompletionRole;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Testing\Factories\EmployeeTaskProgressFactory;
use SupportManager\Sdk\Testing\Factories\TaskFactory;

// Tasks endpoints are plain {data, meta}, not JSON:API — see TaskFactory.

it('lists seeded tasks from a plain {data, meta} payload', function () {
    SupportManager::fake()->seed(
        TaskFactory::new()->count(2),
        TaskFactory::new()->completed()->state(['key' => 'upload_id', 'label' => 'Upload ID']),
    );

    $page = SupportManager::tasks()->all();

    expect($page->items())->toHaveCount(3)
        ->and($page->items()[0])->toBeInstanceOf(Task::class)
        ->and($page->items()[0]->key)->toBe('task_1')
        ->and($page->items()[0]->completionRole)->toBe(TaskCompletionRole::SelfOnly)
        ->and($page->items()[0]->priority)->toBe(10)
        ->and($page->meta()['team_id'])->toBe(42);
});

it('filters tasks by completion status and role', function () {
    SupportManager::fake()->seed(
        TaskFactory::new()->count(2),
        TaskFactory::new()->completed()->state(['key' => 'done_1']),
        TaskFactory::new()->managerOnly()->state(['key' => 'mgr_1']),
    );

    expect(SupportManager::tasks()->all(completed: true)->items())->toHaveCount(1)
        ->and(SupportManager::tasks()->all(completed: false)->items())->toHaveCount(3)
        ->and(SupportManager::tasks()->all(role: TaskCompletionRole::ManagerOnly)->items())->toHaveCount(1);
});

it('derives the badge count and progress from seeded tasks', function () {
    SupportManager::fake()->seed(
        TaskFactory::new()->count(3),
        TaskFactory::new()->completed()->state(['key' => 'done_1']),
        TaskFactory::new()->managerOnly()->state(['key' => 'mgr_1']),
    );

    $count = SupportManager::tasks()->count();
    expect($count)->toBeInstanceOf(TaskCount::class)
        ->and($count->myTasks)->toBe(3)
        ->and($count->managerInbox)->toBe(1)
        ->and($count->count)->toBe(4);

    $progress = SupportManager::tasks()->progress();
    expect($progress)->toBeInstanceOf(TaskProgress::class)
        ->and($progress->total)->toBe(5)
        ->and($progress->completed)->toBe(1)
        ->and($progress->percentage)->toBe(20);
});

it('completes a task by key, flipping its state', function () {
    SupportManager::fake()->seed(TaskFactory::new()->count(2));

    $task = SupportManager::tasks()->complete('task_1');

    expect($task)->toBeInstanceOf(Task::class)
        ->and($task?->key)->toBe('task_1')
        ->and($task?->completed)->toBeTrue()
        ->and(SupportManager::tasks()->progress()->completed)->toBe(1);
});

it('reads the manager inbox and team progress per employee', function () {
    SupportManager::fake()->seed(
        TaskFactory::new()->count(1),
        EmployeeTaskProgressFactory::new()->count(2),
    );

    $inbox = SupportManager::tasks()->managerInbox();
    expect($inbox)->toHaveCount(2)
        ->and($inbox[0])->toBeInstanceOf(EmployeeTaskProgress::class)
        ->and($inbox[0]->userId)->toBe(1)
        ->and($inbox[0]->userName)->toBe('Employee 1')
        ->and($inbox[0]->pendingTasks[0]->key)->toBe('task_1');

    $team = SupportManager::tasks()->teamProgress();
    expect($team)->toHaveCount(2)
        ->and($team[0]->percentage)->toBe(50)
        ->and($team[0]->incompleteTasks[0]->key)->toBe('task_1');
});

it('returns an empty page and 100% progress when nothing is seeded', function () {
    SupportManager::fake()->seed(TaskFactory::new()->count(0));

    expect(SupportManager::tasks()->all()->items())->toBe([])
        ->and(SupportManager::tasks()->progress()->percentage)->toBe(100);
});
