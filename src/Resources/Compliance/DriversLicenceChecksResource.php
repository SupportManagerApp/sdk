<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Compliance;

use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Data\Compliance\DriversLicenceCheck;
use SupportManager\Sdk\Http\ResourceObject;
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
 * SupportManager::driversLicenceChecks()->downloadCheckResultDocument(42)->json('data.download_url');
 * ```
 *
 * @extends \SupportManager\Sdk\Resources\Resource<DriversLicenceCheck>
 */
final class DriversLicenceChecksResource extends Resource
{
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
     * Fetch the download payload for a check's result document. The platform
     * returns a plain JSON body — `{data: {download_url, filename, expires_at}}`
     * — not a JSON:API resource, so the raw {@see Response} is handed back
     * untouched. Read it via `->json('data.download_url')` (a signed link that
     * expires after 5 minutes). The platform 404s when no document has been
     * uploaded, and audits every request.
     */
    public function downloadCheckResultDocument(string|int $id): Response
    {
        return $this->get("{$this->path()}/{$id}/check-result-document");
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
