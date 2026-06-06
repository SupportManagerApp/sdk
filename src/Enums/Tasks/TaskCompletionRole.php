<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Tasks;

/**
 * Who may complete a task, mirroring the platform's `TaskCompletionRole`. The
 * values are what the API stores and accepts as the `completion_role` filter.
 */
enum TaskCompletionRole: string
{
    case SelfOnly = 'self';
    case ManagerOnly = 'manager';
    case Both = 'both';
}
