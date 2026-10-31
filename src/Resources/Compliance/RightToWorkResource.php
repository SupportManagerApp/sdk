<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Compliance;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Data\Compliance\RightToWork;
use SupportManager\Sdk\Enums\Compliance\PrimaryDocumentType;
use SupportManager\Sdk\Http\DownloadLink;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Right-to-work document records.
 *
 * The collection (`query()`, `find()`, `all()`) plus the employee's own
 * self-submission (`submit()`) and `downloadPrimaryDocument()` /
 * `downloadSupportingDocument()` actions that return short-lived download
 * URLs. Filter by `user_id`. Family members and contractors get a 403 on every
 * call.
 *
 * ```php
 * SupportManager::rightToWork()->query()->filter('user_id', 7)->get();
 * SupportManager::rightToWork()->submit(PrimaryDocumentType::BritishPassport, $upload->get(), $upload->getClientOriginalName());
 * SupportManager::rightToWork()->downloadPrimaryDocument(42)->url;
 * ```
 *
 * @extends \SupportManager\Sdk\Resources\Resource<RightToWork>
 */
final class RightToWorkResource extends Resource
{
    /**
     * Create or replace the authenticated user's own right-to-work record for
     * the current team, returning the stored record. Saving also completes the
     * user's right-to-work task server-side.
     *
     * Sent as `multipart/form-data`; documents are PDFs, JPGs or PNGs up to
     * 10MB. What else is required depends on the type (see
     * {@see PrimaryDocumentType}):
     *  - the primary document, unless one is already stored (pass null to keep it);
     *  - `shareCode` for {@see PrimaryDocumentType::ImmigrationShareCode};
     *  - the supporting document for the birth or adoption certificates, unless
     *    one is already stored. For any other type the server removes the
     *    stored supporting document.
     * A missing requirement surfaces as an
     * {@see \SupportManager\Sdk\Exceptions\ApiValidationException} on that field.
     *
     * Files are taken as raw bytes, like
     * {@see \SupportManager\Sdk\Resources\Profile\ProfileResource::uploadAvatar()},
     * so the body can be replayed when the request is retried after a token
     * refresh.
     *
     * @param  string|null  $primaryDocument  The primary document's raw bytes, or null to keep the stored one.
     * @param  string|null  $primaryDocumentFilename  The client filename, e.g. `passport.pdf`.
     * @param  string|null  $supportingDocument  The supporting document's raw bytes, or null to keep the stored one.
     * @param  string|null  $supportingDocumentFilename  The client filename, e.g. `ni-letter.pdf`.
     */
    public function submit(
        PrimaryDocumentType|string $primaryDocumentType,
        ?string $primaryDocument = null,
        ?string $primaryDocumentFilename = null,
        ?string $supportingDocument = null,
        ?string $supportingDocumentFilename = null,
        ?string $shareCode = null,
    ): ?RightToWork {
        $fields = array_filter([
            'primary_document_type' => $primaryDocumentType instanceof PrimaryDocumentType ? $primaryDocumentType->value : $primaryDocumentType,
            'share_code' => $shareCode,
        ], static fn (?string $value): bool => $value !== null);

        return $this->item($this->tenant(function (PendingRequest $request) use ($fields, $primaryDocument, $primaryDocumentFilename, $supportingDocument, $supportingDocumentFilename): Response {
            $request->asMultipart();

            if ($primaryDocument !== null) {
                $request->attach('primary_document', $primaryDocument, $primaryDocumentFilename ?? 'primary-document');
            }

            if ($supportingDocument !== null) {
                $request->attach('supporting_document', $supportingDocument, $supportingDocumentFilename ?? 'supporting-document');
            }

            return $request->post($this->path(), $fields);
        }));
    }

    /**
     * A short-lived link to a record's primary document. The platform answers
     * with `{data: {download_url, filename, expires_at}}` rather than the file,
     * so this returns a {@see DownloadLink}; the URL is signed and expires
     * after 5 minutes. The platform 404s when no document has been uploaded
     * (thrown as a {@see \SupportManager\Sdk\Exceptions\SupportManagerApiException}),
     * and audits every request.
     */
    public function downloadPrimaryDocument(string|int $id): DownloadLink
    {
        return $this->downloadLink("{$this->path()}/{$id}/primary-document");
    }

    /**
     * A short-lived link to a record's supporting document, shaped and
     * behaving exactly like {@see self::downloadPrimaryDocument()}.
     */
    public function downloadSupportingDocument(string|int $id): DownloadLink
    {
        return $this->downloadLink("{$this->path()}/{$id}/supporting-document");
    }

    protected function path(): string
    {
        return 'compliance/right-to-work';
    }

    protected function hydrate(ResourceObject $object): RightToWork
    {
        return RightToWork::fromArray($object->flat());
    }
}
