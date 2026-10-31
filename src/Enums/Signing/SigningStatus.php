<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Signing;

/**
 * Lifecycle state of the document behind a signature request, mirroring the
 * platform's `SigningStatus`. Recipients only ever see sent, completed or
 * voided documents.
 */
enum SigningStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Completed = 'completed';
    case Voided = 'voided';
}
