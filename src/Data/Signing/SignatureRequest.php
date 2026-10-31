<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Signing;

use SupportManager\Sdk\Data\Hydration\Attributes\Can;
use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Attributes\ListOf;
use SupportManager\Sdk\Data\Hydration\Hydrator;
use SupportManager\Sdk\Enums\Signing\SigningChannel;
use SupportManager\Sdk\Enums\Signing\SigningStatus;

/**
 * A document sent to the current user for electronic signature, and their
 * progress on it.
 *
 * Mirrors the platform's `SignatureRequestResource`: the `id` is the
 * recipient's id, which every signing action takes. The nested `document`
 * array is flattened into `document*` fields. `fields` and `consentStatement`
 * are only sent when fetching a single request (or after signing), so a listed
 * request has no fields and a null statement. `canSign` and `canDownloadSigned`
 * come from the resource's `meta.can`.
 */
final readonly class SignatureRequest
{
    /**
     * @param  list<SigningField>  $fields
     */
    public function __construct(
        public string $id,
        #[From('document.name')]
        public string $documentName,
        #[From('document.status')]
        public ?SigningStatus $documentStatus,
        #[From('document.sent_at')]
        public ?string $documentSentAt,
        #[From('document.completed_at')]
        public ?string $documentCompletedAt,
        public ?string $name,
        public ?string $email,
        public ?string $role,
        public ?string $sentAt,
        public ?string $viewedAt,
        public ?string $signedAt,
        public ?SigningChannel $signedVia,
        #[ListOf(SigningField::class)]
        public array $fields,
        public ?string $consentStatement,
        #[Can('sign')]
        public bool $canSign,
        #[Can('download_signed')]
        public bool $canDownloadSigned,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }

    /**
     * Whether the current user has signed.
     */
    public function isSigned(): bool
    {
        return $this->signedAt !== null;
    }

    /**
     * The fields the current user fills in, as opposed to the ones other
     * signers have already filled in.
     *
     * @return list<SigningField>
     */
    public function myFields(): array
    {
        return array_values(array_filter($this->fields, static fn (SigningField $field): bool => $field->isMine));
    }
}
