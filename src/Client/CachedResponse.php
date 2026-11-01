<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Client;

/**
 * One remembered API response for a conditional GET: the platform's `ETag`,
 * and the body and content type to replay when it answers `304`.
 *
 * Plain strings, so a {@see \SupportManager\Sdk\Contracts\ResponseCache} can
 * serialise it however it likes.
 */
final readonly class CachedResponse
{
    public function __construct(
        public string $etag,
        public string $body,
        public string $contentType = 'application/json',
    ) {}
}
