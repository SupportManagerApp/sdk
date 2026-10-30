<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Rota;

/**
 * What a quick-add parse decided the text describes — and so which create
 * endpoint the confirmed result belongs to.
 *
 * The platform documents this list as open, so DTOs construct it with
 * `tryFrom()` and carry null for a value this SDK does not yet know.
 */
enum QuickAddIntent: string
{
    /** A personal calendar event, created with `POST rota/appointments`. */
    case Appointment = 'appointment';

    /** A work shift for a named employee, created with `POST rota/shifts`. */
    case Shift = 'shift';
}
