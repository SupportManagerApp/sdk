<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Compliance;

use SupportManager\Sdk\Data\Compliance\DriversLicenceCheck;
use SupportManager\Sdk\Http\DownloadLink;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Concerns\Findable;
use SupportManager\Sdk\Resources\Concerns\Listable;
use SupportManager\Sdk\Resources\Resource;

/**
 * Driving licence verification records.
 *
 * The collection (`query()`, `find()`, `all()`) plus the employee's own
 * self-submission (`submit()`) and a `downloadCheckResultDocument()` action
 * that returns a short-lived download URL. Filter by `user_id`. Family members
 * and contractors get a 403 on every call.
 *
 * ```php
 * SupportManager::driversLicenceChecks()->query()->filter('user_id', 7)->get();
 * SupportManager::driversLicenceChecks()->submit('HARBO801234AB9CD', 'Xy7Kp2Qm', declarationAccepted: true);
 * SupportManager::driversLicenceChecks()->downloadCheckResultDocument(42)->url;
 * ```
 *
 * @extends Resource<DriversLicenceCheck>
 */
final class DriversLicenceChecksResource extends Resource
{
    /** @use Findable<DriversLicenceCheck> */
    use Findable;

    /** @use Listable<DriversLicenceCheck> */
    use Listable;

    /**
     * Create or replace the authenticated user's own driving licence details
     * for the current team, returning the stored record. Saving also completes
     * the user's driving licence task server-side.
     *
     * The server requires the declaration to be accepted; passing false
     * surfaces as an {@see \SupportManager\Sdk\Exceptions\ApiValidationException}
     * on `declaration_accepted`.
     *
     * @param  string  $checkCode  The DVLA share-licence check code.
     */
    public function submit(string $driversLicenceNumber, string $checkCode, bool $declarationAccepted): ?DriversLicenceCheck
    {
        return $this->item($this->post($this->path(), [
            'drivers_licence_number' => $driversLicenceNumber,
            'check_code' => $checkCode,
            'declaration_accepted' => $declarationAccepted,
        ]));
    }

    /**
     * A short-lived link to a check's result document. The platform answers
     * with `{data: {download_url, filename, expires_at}}` rather than the file,
     * so this returns a {@see DownloadLink}; the URL is signed and expires
     * after 5 minutes. The platform 404s when no document has been uploaded
     * (thrown as a {@see \SupportManager\Sdk\Exceptions\SupportManagerApiException}),
     * and audits every request.
     */
    public function downloadCheckResultDocument(string|int $id): DownloadLink
    {
        return $this->downloadLink("{$this->path()}/{$id}/check-result-document");
    }

    protected function path(): string
    {
        return 'compliance/drivers-licence-checks';
    }

    protected function hydrate(ResourceObject $object): DriversLicenceCheck
    {
        return DriversLicenceCheck::fromArray($object->flat());
    }
}
