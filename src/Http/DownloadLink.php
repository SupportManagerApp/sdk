<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Http;

use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Hydrator;

/**
 * A short-lived link to a file, for endpoints that answer with
 * `{data: {download_url, filename, expires_at}}` rather than the file itself.
 * Compliance documents, team documents and signed copies work this way.
 *
 * The URL is a signed storage link that the platform currently issues for
 * five minutes, so fetch it straight away and don't cache it. Endpoints that
 * return the bytes themselves give a {@see Download} instead.
 *
 * ```php
 * $link = SupportManager::documents()->download(7);
 * $link->url;       // "https://…/contract.pdf?X-Amz-Signature=…"
 * $link->filename;  // "contract.pdf"
 * $link->expiresAt; // "2026-10-31T14:35:00.000000Z"
 * ```
 */
final readonly class DownloadLink
{
    /**
     * @param  string  $url  The signed download URL.
     * @param  string|null  $filename  The file's display name.
     * @param  string|null  $expiresAt  When the URL stops working (ISO 8601).
     */
    public function __construct(
        #[From('download_url')]
        public string $url,
        public ?string $filename = null,
        public ?string $expiresAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The response `data` member.
     */
    public static function fromArray(array $data): self
    {
        return Hydrator::hydrate(self::class, $data);
    }
}
