<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Signing;

use Override;
use SupportManager\Sdk\Data\Signing\SignatureRequest;
use SupportManager\Sdk\Enums\Signing\SignatureRequestStatus;
use SupportManager\Sdk\Http\Download;
use SupportManager\Sdk\Http\DownloadLink;
use SupportManager\Sdk\Http\Paginated;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * E-signing — documents sent to the current user to fill in and sign.
 *
 * `all()` lists the user's own requests (the inbox by default, or the ones
 * they have signed); `find()` fetches one with its fields and consent
 * statement (and marks it viewed). The signing flow is `file()` for the
 * original PDF, `saveValues()` as the user fills fields in, then `sign()`;
 * `downloadSigned()` gets a short-lived link to the completed copy. Preparing
 * and sending documents is web-only. The platform 404s every endpoint while
 * the team has E-signing switched off.
 *
 * ```php
 * SupportManager::signatureRequests()->all()->items();
 * SupportManager::signatureRequests()->find(12)?->myFields();
 * SupportManager::signatureRequests()->saveValues(12, ['6f1c…' => 'data:image/png;base64,…']);
 * SupportManager::signatureRequests()->sign(12, agreesToSignElectronically: true);
 * SupportManager::signatureRequests()->downloadSigned(12)->url;
 * ```
 *
 * @extends \SupportManager\Sdk\Resources\Resource<SignatureRequest>
 */
final class SignatureRequestsResource extends Resource
{
    /**
     * The current user's signature requests: still awaiting their signature
     * (oldest first, the default) or already signed (most recent first). The
     * endpoint is not paginated; the whole list is wrapped in {@see Paginated}
     * for consistency with other resources. Listed requests carry no fields.
     *
     * @return Paginated<SignatureRequest>
     */
    #[Override]
    public function all(?SignatureRequestStatus $status = null): Paginated
    {
        return $this->paginated($this->get($this->path(), $status === null ? [] : ['status' => $status->value]));
    }

    /**
     * Download the original document, as sent, without anything filled in, so
     * the host can draw the fields over it. The response body is the PDF
     * itself, so it comes back as a {@see Download}; an error response throws.
     */
    public function file(string|int $id): Download
    {
        return $this->downloadFile("{$this->path()}/{$id}/file");
    }

    /**
     * Save what the user has filled in so far, keyed by field id. Send only the
     * fields that changed; null clears one. Required fields are only checked
     * when signing. The platform answers 204, so nothing is returned; a value
     * that does not suit its field throws an
     * {@see \SupportManager\Sdk\Exceptions\ApiValidationException} keyed by
     * field id.
     *
     * @param  array<string, string|null>  $values
     */
    public function saveValues(string|int $id, array $values): void
    {
        $this->throwForErrorResponse($this->put("{$this->path()}/{$id}/values", $values));
    }

    /**
     * Sign using the values already saved, returning the updated request. Show
     * the user {@see SignatureRequest::$consentStatement} and pass true once
     * they agree; false is refused with a `consent` validation error.
     */
    public function sign(string|int $id, bool $agreesToSignElectronically): ?SignatureRequest
    {
        return $this->item($this->post("{$this->path()}/{$id}/sign", [
            'agrees_to_sign_electronically' => $agreesToSignElectronically,
        ]));
    }

    /**
     * A short-lived link to the signed copy, with its signing certificate. The
     * platform answers with `{data: {download_url, filename, expires_at}}`
     * rather than the file, so this returns a {@see DownloadLink}; the URL
     * expires after five minutes. Before the document is complete the platform
     * answers 403, which throws.
     */
    public function downloadSigned(string|int $id): DownloadLink
    {
        return $this->downloadLink("{$this->path()}/{$id}/signed");
    }

    protected function path(): string
    {
        return 'signing/requests';
    }

    protected function hydrate(ResourceObject $object): SignatureRequest
    {
        return SignatureRequest::fromArray($object->flat());
    }
}
