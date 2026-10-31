<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Compliance;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Data\Compliance\DBSCheck;
use SupportManager\Sdk\Http\DownloadLink;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * DBS (Disclosure and Barring Service) check records.
 *
 * The collection (`query()`, `find()`, `all()`) plus the employee's own
 * self-submission (`submit()`) and a `downloadCertificate()` action that
 * returns a short-lived download URL. Filter by `user_id` and sort by
 * `date_of_issue` / `created_at`. Family members and contractors get a 403 on
 * every call.
 *
 * ```php
 * SupportManager::dbsChecks()->query()->filter('user_id', 7)->sort('-date_of_issue')->get();
 * SupportManager::dbsChecks()->submit('2026-03-14', '001234567890', $upload->get(), $upload->getClientOriginalName());
 * SupportManager::dbsChecks()->downloadCertificate(42)->url;
 * ```
 *
 * @extends \SupportManager\Sdk\Resources\Resource<DBSCheck>
 */
final class DBSChecksResource extends Resource
{
    /**
     * Create or replace the authenticated user's own DBS check for the current
     * team, returning the stored record. Saving also completes the user's DBS
     * check task server-side.
     *
     * Sent as `multipart/form-data`. The certificate (a PDF, JPG or PNG up to
     * 10MB) is required only when none is stored yet — pass null to keep the
     * stored one. A missing or bad file surfaces as an
     * {@see \SupportManager\Sdk\Exceptions\ApiValidationException} on
     * `certificate`.
     *
     * Takes the raw bytes rather than a path or stream, like
     * {@see \SupportManager\Sdk\Resources\Profile\ProfileResource::uploadAvatar()},
     * so the body can be replayed when the request is retried after a token
     * refresh.
     *
     * @param  string  $dateOfIssue  The certificate's issue date (`Y-m-d`).
     * @param  string|null  $certificate  The certificate file's raw bytes, or null to keep the stored one.
     * @param  string|null  $certificateFilename  The client filename, e.g. `dbs.pdf`.
     */
    public function submit(string $dateOfIssue, string $certificateNumber, ?string $certificate = null, ?string $certificateFilename = null): ?DBSCheck
    {
        return $this->item($this->tenant(function (PendingRequest $request) use ($dateOfIssue, $certificateNumber, $certificate, $certificateFilename): Response {
            $request->asMultipart();

            if ($certificate !== null) {
                $request->attach('certificate', $certificate, $certificateFilename ?? 'certificate');
            }

            return $request->post($this->path(), [
                'date_of_issue' => $dateOfIssue,
                'certificate_number' => $certificateNumber,
            ]);
        }));
    }

    /**
     * A short-lived link to a check's certificate. The platform answers with
     * `{data: {download_url, filename, expires_at}}` rather than the file, so
     * this returns a {@see DownloadLink}; the URL is signed and expires after
     * 5 minutes. The platform 404s when no certificate has been uploaded
     * (thrown as a {@see \SupportManager\Sdk\Exceptions\SupportManagerApiException}),
     * and audits every request.
     */
    public function downloadCertificate(string|int $id): DownloadLink
    {
        return $this->downloadLink("{$this->path()}/{$id}/certificate");
    }

    protected function path(): string
    {
        return 'compliance/dbs-checks';
    }

    protected function hydrate(ResourceObject $object): DBSCheck
    {
        return DBSCheck::fromArray($object->flat());
    }
}
