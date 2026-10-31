<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Tasks;

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
 * objects), so they are read with the base class's plain-body helpers
 * ({@see Resource::data()} and friends) rather than through the fluent
 * `query()` builder, which this resource does not have.
 *
 * ```php
 * SupportManager::tasks()->all()->items();
 * SupportManager::tasks()->count()->count;
 * SupportManager::tasks()->complete('payroll.bank_details');
 * ```
 *
 * @extends Resource<Task>
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
    public function all(?bool $completed = null, ?TaskCompletionRole $role = null): Paginated
    {
        $query = [];

        if ($completed !== null) {
            $query['completed'] = $completed ? 'true' : 'false';
        }

        if ($role !== null) {
            $query['completion_role'] = $role->value;
        }

        return $this->dataPaginated($this->get($this->path(), $query), Task::class);
    }

    /**
     * The dashboard task badge counts.
     */
    public function count(): TaskCount
    {
        return TaskCount::fromArray($this->raw($this->get("{$this->path()}/count")));
    }

    /**
     * The current user's completion progress.
     */
    public function progress(): TaskProgress
    {
        return TaskProgress::fromArray($this->raw($this->get("{$this->path()}/progress")));
    }

    /**
     * Employees with pending manager-only tasks (privileged).
     *
     * @return list<EmployeeTaskProgress>
     */
    public function managerInbox(): array
    {
        return $this->dataList($this->get("{$this->path()}/manager-inbox"), EmployeeTaskProgress::class);
    }

    /**
     * Task progress for every active employee in the team (privileged).
     *
     * @return list<EmployeeTaskProgress>
     */
    public function teamProgress(): array
    {
        return $this->dataList($this->get("{$this->path()}/team-progress"), EmployeeTaskProgress::class);
    }

    /**
     * Mark a task complete by its key, optionally on behalf of another user
     * (managers only). Returns the task's fresh state, or null if the response
     * carried no task body.
     *
     * A task is really completed by saving the record it asks for through its
     * own module (e.g. `profile.emergency_contact` via
     * {@see \SupportManager\Sdk\Resources\Profile\ProfileResource::saveEmergencyContacts()});
     * this call only refreshes the cached counts afterwards. A `$userId` who is
     * not a member of the current team is rejected with a 422.
     */
    public function complete(string $taskKey, string|int|null $userId = null): ?Task
    {
        return $this->data($this->post("{$this->path()}/{$taskKey}/complete", array_filter([
            'user_id' => $userId,
        ], static fn (mixed $value): bool => $value !== null)), Task::class);
    }

    protected function path(): string
    {
        return 'tasks';
    }

    protected function hydrate(ResourceObject $object): Task
    {
        return Task::fromArray($object->flat());
    }
}
