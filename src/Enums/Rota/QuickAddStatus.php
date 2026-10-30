<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Rota;

/**
 * Where a quick-add parse has got to, mirroring the `status` the platform's
 * `QuickAddController` reports for a token.
 *
 * The platform documents this list as open, so DTOs construct it with
 * `tryFrom()` and carry null for a value this SDK does not yet know.
 */
enum QuickAddStatus: string
{
    /** The job has not published yet — keep polling. */
    case Pending = 'pending';

    /** The parse finished and `result` is populated. */
    case Completed = 'completed';

    /** The parser couldn't answer; `hint` is a sentence to show the user. */
    case Failed = 'failed';
}
