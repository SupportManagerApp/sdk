<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Leave;

/**
 * Lifecycle state of a leave request, mirroring the platform's `LeaveStatus`.
 */
enum LeaveStatus: string
{
    case Pending = 'Pending';
    case Approved = 'Approved';
    case Rejected = 'Rejected';
    case Cancelled = 'Cancelled';
}
