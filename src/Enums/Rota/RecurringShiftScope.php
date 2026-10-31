<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Rota;

/**
 * Which shifts of a repeating series an edit or delete reaches. Mirrors the
 * platform's `UpdateRecurringShiftAction`/`DeleteRecurringShiftAction`
 * `SCOPE_*` constants. A shift that doesn't repeat ignores it.
 */
enum RecurringShiftScope: string
{
    /** This shift only. An edit detaches it from its series. */
    case Single = 'single';

    /** This shift and every later one in its series. */
    case Future = 'future';
}
