<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Signing;

use Illuminate\Http\Client\Response;
use Override;
use SupportManager\Sdk\Data\Signing\SignatureRequest;
use SupportManager\Sdk\Enums\Signing\SignatureRequestStatus;
use SupportManager\Sdk\Http\Document;
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
 * SupportManager::signatureRequests()->downloadSigned(12)->json('data.download_url');
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
        $response = $this->get($this->path(), $status === null ? [] : ['status' => $status->value]);
        $items = $this->collection($response);

        return new Paginated(
            items: $items,
            meta: Document::fromArray($response->json() ?? [])->meta(),
        );
    }

    /**
     * Download the original document, as sent, without anything filled in.
     * Returns the raw binary {@see Response} — the body is the PDF, not a
     * JSON:API document — so the host draws the fields over it.
     */
    public function file(string|int $id): Response
    {
        return $this->get("{$this->path()}/{$id}/file");
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
        $uri = "{$this->path()}/{$id}/values";

        $this->throwForErrorResponse($this->put($uri, $values), $uri);
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
     * Fetch the download payload for the signed copy, with its signing
     * certificate. The platform returns a plain JSON body —
     * `{data: {download_url, filename, expires_at}}` — not a JSON:API resource,
     * so the raw {@see Response} is handed back untouched. Read it via
     * `->json('data.download_url')` (the URL expires after five minutes).
     */
    public function downloadSigned(string|int $id): Response
    {
        return $this->get("{$this->path()}/{$id}/signed");
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
