<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Tasks;

use SupportManager\Sdk\Data\Hydration\Attributes\Defaults;
use SupportManager\Sdk\Data\Hydration\Hydrator;
use SupportManager\Sdk\Enums\Tasks\TaskCompletionRole;

/**
 * A single onboarding/compliance task surfaced on the dashboard, mirroring the
 * platform's `TaskResource`.
 *
 * `key` identifies the task for completion (see
 * {@see \SupportManager\Sdk\Resources\Tasks\TasksResource::complete()});
 * `completionRole` is who may complete it.
 */
final readonly class Task
{
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public string $icon,
        public bool $completed,
        #[Defaults(TaskCompletionRole::SelfOnly)]
        public TaskCompletionRole $completionRole,
        public ?string $completionRoleLabel,
        public int $priority,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A task entry from the response `data`.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
