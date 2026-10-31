<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Signing;

/**
 * Which of the user's signature requests to list — the `status` query the
 * platform's signature request index accepts. The platform defaults to
 * {@see self::Awaiting}.
 */
enum SignatureRequestStatus: string
{
    case Awaiting = 'awaiting';
    case Signed = 'signed';
}
