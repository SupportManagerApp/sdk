<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Accidents;

/**
 * The capacity someone outside the team was there in, mirroring the platform's
 * `AccidentSubjectCapacity`. Sent as `subject.capacity` for an
 * {@see AccidentSubjectType::SomeoneElse} subject, and returned as the report's
 * `subject.occupation`.
 */
enum AccidentSubjectCapacity: string
{
    case Visitor = 'Visitor';
    case Family = 'Family';
    case Contractor = 'Contractor';
    case Other = 'Other';
}
