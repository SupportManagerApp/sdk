<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Accidents;

/**
 * Who had the accident being recorded, mirroring the platform's
 * `AccidentSubjectType`. The values are what `subject.type` accepts when
 * creating a report.
 */
enum AccidentSubjectType: string
{
    case Reporter = 'me';
    case TeamMember = 'team_member';
    case SomeoneElse = 'other';
}
