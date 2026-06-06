<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Rota;

/**
 * The kind of a rota entry, mirroring the platform's `RotaEntryType`.
 *
 * The values are the human labels the platform stores and serialises (e.g.
 * `Contracted Hours`), so this can be constructed straight from the API's
 * `type` attribute.
 */
enum EntryType: string
{
    case ContractedHours = 'Contracted Hours';
    case OvertimeHours = 'Overtime Hours';
    case AnnualLeaveHours = 'Annual Leave Hours';
    case ContractedSleepingNight = 'Contracted Sleeping Night';
    case OvertimeSleepingNight = 'Overtime Sleeping Night';
    case AnnualLeaveSleepingNight = 'Annual Leave Sleeping Night';
    case Sickness = 'Sickness';
    case UnpaidLeave = 'Unpaid Leave';
    case Appointment = 'Appointment';
}
