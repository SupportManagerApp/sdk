<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Leave;

/**
 * Whether a leave request covers whole days or specific custom timings,
 * mirroring the platform's `LeaveDurationType`.
 */
enum LeaveDurationType: string
{
    case WholeDays = 'Whole Days';
    case CustomTimings = 'Custom Timings';
}
