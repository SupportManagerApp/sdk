<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Signing;

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
        public string $documentName,
        public ?SigningStatus $documentStatus,
        public ?string $documentSentAt,
        public ?string $documentCompletedAt,
        public ?string $name,
        public ?string $email,
        public ?string $role,
        public ?string $sentAt,
        public ?string $viewedAt,
        public ?string $signedAt,
        public ?SigningChannel $signedVia,
        public array $fields,
        public ?string $consentStatement,
        public bool $canSign,
        public bool $canDownloadSigned,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     * @param  array<string, mixed>  $meta  The resource object's own `meta` member.
     */
    public static function fromArray(array $data, array $meta = []): self
    {
        /** @var array{name?: mixed, status?: mixed, sent_at?: mixed, completed_at?: mixed} $document */
        $document = is_array($data['document'] ?? null) ? $data['document'] : [];

        /** @var array{sign?: mixed, download_signed?: mixed} $can */
        $can = is_array($meta['can'] ?? null) ? $meta['can'] : [];

        $fields = is_array($data['fields'] ?? null) ? $data['fields'] : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            documentName: (string) ($document['name'] ?? ''),
            documentStatus: isset($document['status']) ? SigningStatus::tryFrom((string) $document['status']) : null,
            documentSentAt: isset($document['sent_at']) ? (string) $document['sent_at'] : null,
            documentCompletedAt: isset($document['completed_at']) ? (string) $document['completed_at'] : null,
            name: isset($data['name']) ? (string) $data['name'] : null,
            email: isset($data['email']) ? (string) $data['email'] : null,
            role: isset($data['role']) ? (string) $data['role'] : null,
            sentAt: isset($data['sent_at']) ? (string) $data['sent_at'] : null,
            viewedAt: isset($data['viewed_at']) ? (string) $data['viewed_at'] : null,
            signedAt: isset($data['signed_at']) ? (string) $data['signed_at'] : null,
            signedVia: isset($data['signed_via']) ? SigningChannel::tryFrom((string) $data['signed_via']) : null,
            fields: array_values(array_map(
                static fn (mixed $field): SigningField => SigningField::fromArray(is_array($field) ? $field : []),
                $fields,
            )),
            consentStatement: isset($data['consent_statement']) ? (string) $data['consent_statement'] : null,
            canSign: (bool) ($can['sign'] ?? false),
            canDownloadSigned: (bool) ($can['download_signed'] ?? false),
        );
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
