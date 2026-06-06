<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Exceptions;

use SupportManager\Sdk\Data\DeviceVerificationChallenge;

/**
 * Thrown on a 403 with error_code DEVICE_VERIFICATION_REQUIRED. Carries the
 * challenge so the caller can drive the SMS/email/passkey verification flow.
 */
final class DeviceVerificationRequiredException extends SupportManagerApiException
{
    public function __construct(
        public readonly DeviceVerificationChallenge $challenge,
        string $message = 'Device verification required.',
    ) {
        parent::__construct($message, 403);
    }
}
