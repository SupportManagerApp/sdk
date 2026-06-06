<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Tasks\EmployeeTaskProgress;
use SupportManager\Sdk\Data\Tasks\Task;
use SupportManager\Sdk\Data\Tasks\TaskCount;
use SupportManager\Sdk\Data\Tasks\TaskProgress;
use SupportManager\Sdk\Enums\Tasks\TaskCompletionRole;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Resources\Tasks\TasksResource;

function tasks(): TasksResource
{
    return app(TasksResource::class);
}

function taskEntry(string $key, bool $completed = false, string $role = 'self'): array
{
    return [
        'key' => $key,
        'label' => ucfirst(str_replace('_', ' ', $key)),
        'description' => "Do {$key}",
        'icon' => 'check',
        'completed' => $completed,
        'completion_role' => $role,
        'completion_role_label' => 'Employee Only',
        'priority' => 10,
    ];
}

it('lists the user tasks from a flat data payload', function () {
    Http::fake(['api.test/api/v1/tasks/tasks*' => Http::response([
        'data' => [taskEntry('verify_bank_details'), taskEntry('upload_id', completed: true)],
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    $page = tasks()->all();

    expect($page->items())->toHaveCount(2)
        ->and($page->items()[0])->toBeInstanceOf(Task::class)
        ->and($page->items()[0]->key)->toBe('verify_bank_details')
        ->and($page->items()[0]->completionRole)->toBe(TaskCompletionRole::SelfOnly)
        ->and($page->items()[0]->priority)->toBe(10)
        ->and($page->items()[1]->completed)->toBeTrue()
        ->and($page->meta()['team_id'])->toBe(42);

    Http::assertSent(fn ($r) => $r->method() === 'GET' && $r->hasHeader('X-Team-ID', '42'));
});

it('filters tasks by completion status and role', function () {
    Http::fake(['api.test/api/v1/tasks/tasks*' => Http::response(['data' => [], 'meta' => []])]);
    bootTenantSession();

    tasks()->all(completed: false, role: TaskCompletionRole::ManagerOnly);

    Http::assertSent(function ($r) {
        $url = urldecode($r->url());

        return str_contains($url, 'completed=false') && str_contains($url, 'completion_role=manager');
    });
});

it('reads the task badge count', function () {
    Http::fake(['api.test/api/v1/tasks/tasks/count' => Http::response([
        'data' => ['count' => 5, 'my_tasks' => 3, 'manager_inbox' => 2],
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    $count = tasks()->count();

    expect($count)->toBeInstanceOf(TaskCount::class)
        ->and($count->count)->toBe(5)
        ->and($count->myTasks)->toBe(3)
        ->and($count->managerInbox)->toBe(2);
});

it('reads the user progress', function () {
    Http::fake(['api.test/api/v1/tasks/tasks/progress' => Http::response([
        'data' => ['percentage' => 75, 'completed' => 3, 'total' => 4],
    ])]);
    bootTenantSession();

    $progress = tasks()->progress();

    expect($progress)->toBeInstanceOf(TaskProgress::class)
        ->and($progress->percentage)->toBe(75)
        ->and($progress->completed)->toBe(3)
        ->and($progress->total)->toBe(4);
});

it('reads the manager inbox with pending tasks per employee', function () {
    Http::fake(['api.test/api/v1/tasks/tasks/manager-inbox' => Http::response([
        'data' => [
            ['user' => ['id' => 7, 'name' => 'Bob'], 'pending_tasks' => [taskEntry('approve_leave', role: 'manager')]],
        ],
        'meta' => ['team_id' => 42, 'total_pending' => 1],
    ])]);
    bootTenantSession();

    $inbox = tasks()->managerInbox();

    expect($inbox)->toHaveCount(1)
        ->and($inbox[0])->toBeInstanceOf(EmployeeTaskProgress::class)
        ->and($inbox[0]->userId)->toBe(7)
        ->and($inbox[0]->userName)->toBe('Bob')
        ->and($inbox[0]->pendingTasks)->toHaveCount(1)
        ->and($inbox[0]->pendingTasks[0]->key)->toBe('approve_leave');
});

it('reads team progress with percentage and incomplete tasks', function () {
    Http::fake(['api.test/api/v1/tasks/tasks/team-progress' => Http::response([
        'data' => [
            ['user' => ['id' => 7, 'name' => 'Bob'], 'percentage' => 50, 'incomplete_tasks' => [taskEntry('upload_id')]],
        ],
    ])]);
    bootTenantSession();

    $progress = tasks()->teamProgress();

    expect($progress[0]->percentage)->toBe(50)
        ->and($progress[0]->incompleteTasks)->toHaveCount(1)
        ->and($progress[0]->incompleteTasks[0]->key)->toBe('upload_id')
        ->and($progress[0]->pendingTasks)->toBe([]);
});

it('completes a task by key', function () {
    Http::fake(['api.test/api/v1/tasks/tasks/verify_bank_details/complete' => Http::response([
        'message' => 'Task cache cleared successfully.',
        'data' => taskEntry('verify_bank_details', completed: true),
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    $task = tasks()->complete('verify_bank_details');

    expect($task)->toBeInstanceOf(Task::class)
        ->and($task->key)->toBe('verify_bank_details')
        ->and($task->completed)->toBeTrue();

    Http::assertSent(fn ($r) => $r->method() === 'POST'
        && str_ends_with($r->url(), 'tasks/verify_bank_details/complete'));
});

it('completes a task on behalf of another user', function () {
    Http::fake(['api.test/api/v1/tasks/tasks/approve_leave/complete' => Http::response([
        'message' => 'Task cache cleared successfully.',
        'data' => ['key' => 'approve_leave', 'completed' => true],
    ])]);
    bootTenantSession();

    $task = tasks()->complete('approve_leave', 7);

    expect($task->key)->toBe('approve_leave')
        ->and($task->completed)->toBeTrue();
    Http::assertSent(fn ($r) => $r['user_id'] === 7);
});

it('throws when completing an unknown task (404)', function () {
    Http::fake(['api.test/api/v1/tasks/tasks/nope/complete' => Http::response(['message' => 'Task not found.'], 404)]);
    bootTenantSession();

    tasks()->complete('nope');
})->throws(SupportManagerApiException::class);
