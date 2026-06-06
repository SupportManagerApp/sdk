<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Rota;

/**
 * Lifecycle state of a shift swap request, mirroring the platform's
 * `ShiftSwapStatus`.
 *
 * The values are the human labels the platform stores and serialises, so this
 * can be constructed straight from the API's `status` attribute.
 */
enum ShiftSwapStatus: string
{
    case PendingResponse = 'Pending Response';
    case OpenForOffers = 'Open for Offers';
    case PendingRequesterApproval = 'Pending Requester Approval';
    case BothAgreed = 'Both Agreed';
    case PendingManagerApproval = 'Pending Manager Approval';
    case Approved = 'Approved';
    case Declined = 'Declined';
    case Cancelled = 'Cancelled';
    case Expired = 'Expired';
    case Rejected = 'Rejected';
}
