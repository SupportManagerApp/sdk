<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Compliance;

/**
 * The primary document a right-to-work record is evidenced by. Mirrors the
 * platform's `PrimaryDocumentType` enum; the backing values are the
 * human-readable labels the API stores and returns.
 *
 * The type decides what else {@see \SupportManager\Sdk\Resources\Compliance\RightToWorkResource::submit()}
 * needs: a share code for {@see self::ImmigrationShareCode}, and a supporting
 * document for the two birth or adoption certificates.
 */
enum PrimaryDocumentType: string
{
    case BritishPassport = 'British Passport';
    case UkBirthOrAdoptionCertificate = 'UK Birth or Adoption Certificate';
    case IrishBirthOrAdoptionCertificate = 'Irish Birth or Adoption Certificate';
    case ImmigrationPapers = 'Immigration Papers';
    case ImmigrationShareCode = 'Immigration Share Code';

    /**
     * Whether a submission of this type must carry a `share_code`.
     */
    public function requiresShareCode(): bool
    {
        return $this === self::ImmigrationShareCode;
    }

    /**
     * Whether a submission of this type must carry a supporting document
     * (unless one is already stored).
     */
    public function requiresSupportingDocument(): bool
    {
        return $this === self::UkBirthOrAdoptionCertificate
            || $this === self::IrishBirthOrAdoptionCertificate;
    }
}
