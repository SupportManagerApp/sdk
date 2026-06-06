<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Tasks;

use Illuminate\Http\Client\Response;
use Override;
use SupportManager\Sdk\Data\Tasks\EmployeeTaskProgress;
use SupportManager\Sdk\Data\Tasks\Task;
use SupportManager\Sdk\Data\Tasks\TaskCount;
use SupportManager\Sdk\Data\Tasks\TaskProgress;
use SupportManager\Sdk\Enums\Tasks\TaskCompletionRole;
use SupportManager\Sdk\Http\Paginated;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * The dashboard task list — onboarding/compliance steps the user (and, for
 * managers, their team) needs to complete.
 *
 * `all()` lists the current user's tasks (optionally filtered); `count()` and
 * `progress()` give the badge/summary figures; `managerInbox()` and
 * `teamProgress()` are the privileged manager views; and `complete()` clears a
 * task by its key.
 *
 * These endpoints return plain `{data, meta}` payloads (not JSON:API resource
 * objects), so they are parsed straight from `data` rather than through the
 * fluent {@see query()} builder.
 *
 * ```php
 * SupportManager::tasks()->all()->items();
 * SupportManager::tasks()->count()->count;
 * SupportManager::tasks()->complete('verify_bank_details');
 * ```
 *
 * @extends \SupportManager\Sdk\Resources\Resource<Task>
 */
final class TasksResource extends Resource
{
    /**
     * The current user's tasks, optionally filtered by completion status and/or
     * the role that may complete them. The endpoint is not paginated; the whole
     * list is wrapped in {@see Paginated} for consistency with other resources.
     *
     * @return Paginated<Task>
     */
    #[Override]
    public function all(?bool $completed = null, ?TaskCompletionRole $role = null): Paginated
    {
        $query = [];

        if ($completed !== null) {
            $query['completed'] = $completed ? 'true' : 'false';
        }

        if ($role !== null) {
            $query['completion_role'] = $role->value;
        }

        $response = $this->get($this->path(), $query);
        $this->throwForErrorResponse($response, $this->path());

        return new Paginated(
            items: $this->tasksFrom($response),
            meta: $this->metaFrom($response),
        );
    }

    /**
     * The dashboard task badge counts.
     */
    public function count(): TaskCount
    {
        return TaskCount::fromArray($this->data("{$this->path()}/count"));
    }

    /**
     * The current user's completion progress.
     */
    public function progress(): TaskProgress
    {
        return TaskProgress::fromArray($this->data("{$this->path()}/progress"));
    }

    /**
     * Employees with pending manager-only tasks (privileged).
     *
     * @return list<EmployeeTaskProgress>
     */
    public function managerInbox(): array
    {
        return $this->employeeProgressFrom("{$this->path()}/manager-inbox");
    }

    /**
     * Task progress for every active employee in the team (privileged).
     *
     * @return list<EmployeeTaskProgress>
     */
    public function teamProgress(): array
    {
        return $this->employeeProgressFrom("{$this->path()}/team-progress");
    }

    /**
     * Mark a task complete by its key, optionally on behalf of another user
     * (managers only). Returns the task's fresh state, or null if the response
     * carried no task body.
     */
    public function complete(string $taskKey, string|int|null $userId = null): ?Task
    {
        $response = $this->post("{$this->path()}/{$taskKey}/complete", array_filter([
            'user_id' => $userId,
        ], static fn (mixed $value): bool => $value !== null));
        $this->throwForErrorResponse($response, $this->path());

        $data = $response->json('data');

        return is_array($data) ? Task::fromArray($data) : null;
    }

    protected function path(): string
    {
        return 'tasks/tasks';
    }

    protected function hydrate(ResourceObject $object): Task
    {
        return Task::fromArray($object->flat());
    }

    /**
     * GET a flat `{data, meta}` endpoint and return its `data` object.
     *
     * @return array<string, mixed>
     */
    private function data(string $uri): array
    {
        $response = $this->get($uri);
        $this->throwForErrorResponse($response, $uri);

        /** @var array<string, mixed> $data */
        $data = $response->json('data', []);

        return $data;
    }

    /**
     * @return list<Task>
     */
    private function tasksFrom(Response $response): array
    {
        /** @var list<array<string, mixed>> $data */
        $data = $response->json('data', []);

        return array_values(array_map(Task::fromArray(...), $data));
    }

    /**
     * @return list<EmployeeTaskProgress>
     */
    private function employeeProgressFrom(string $uri): array
    {
        $response = $this->get($uri);
        $this->throwForErrorResponse($response, $uri);

        /** @var list<array<string, mixed>> $data */
        $data = $response->json('data', []);

        return array_values(array_map(EmployeeTaskProgress::fromArray(...), $data));
    }

    /**
     * @return array<string, mixed>
     */
    private function metaFrom(Response $response): array
    {
        /** @var array<string, mixed> $meta */
        $meta = $response->json('meta', []);

        return $meta;
    }
}
