<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Documents;

/**
 * Whether a document or folder belongs to one team member (`personal`) or to
 * the team (`team`). Mirrors the platform's `DocumentScope` enum; the list is
 * open, so DTOs decode unknown values to null rather than failing.
 */
enum DocumentScope: string
{
    case Personal = 'personal';
    case Team = 'team';
}
