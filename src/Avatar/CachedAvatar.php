<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Avatar;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use JsonException;

/**
 * Stores profile avatar bytes on the host app's local disk so short-lived
 * signed avatar URLs do not need to be fetched on every profile view.
 *
 * The cache and data-URI rendering live here so every client app behaves
 * identically; serving the bytes over HTTP (a presentation concern) is left to
 * the host — call {@see self::payload()} to get the bytes and content type.
 */
final class CachedAvatar
{
    private const DISK = 'local';

    private const DIRECTORY = 'auth/avatars';

    public function cachedUrl(int $userId): ?string
    {
        return $this->dataUri($userId);
    }

    public function remember(int $userId, ?string $avatarUrl): ?string
    {
        if ($this->isFresh($userId)) {
            return $this->dataUri($userId);
        }

        if ($avatarUrl === null || $avatarUrl === '') {
            return $this->dataUri($userId);
        }

        try {
            $response = Http::timeout(10)->get($avatarUrl);
        } catch (ConnectionException) {
            return $this->dataUri($userId);
        }

        $contentType = $this->detectContentType($response->body(), $response->header('Content-Type'));

        if (! $response->successful() || $response->body() === '' || $contentType === null) {
            return $this->dataUri($userId);
        }

        Storage::disk(self::DISK)->put($this->imagePath($userId), $response->body());
        Storage::disk(self::DISK)->put($this->metaPath($userId), json_encode([
            'content_type' => $contentType,
            'fetched_at' => time(),
        ], JSON_THROW_ON_ERROR));

        return $this->dataUri($userId);
    }

    /**
     * The cached bytes and content type for serving over HTTP, or null when no
     * avatar is cached. Host apps build their own HTTP response from this.
     *
     * @return array{body: string, content_type: string}|null
     */
    public function payload(int $userId): ?array
    {
        if (! $this->exists($userId)) {
            return null;
        }

        return [
            'body' => (string) Storage::disk(self::DISK)->get($this->imagePath($userId)),
            'content_type' => $this->contentType($userId),
        ];
    }

    public function clear(int $userId): void
    {
        Storage::disk(self::DISK)->delete([
            $this->imagePath($userId),
            $this->metaPath($userId),
        ]);
    }

    private function exists(int $userId): bool
    {
        return Storage::disk(self::DISK)->exists($this->imagePath($userId));
    }

    private function isFresh(int $userId): bool
    {
        $metadata = $this->metadata($userId);

        if (! $this->exists($userId) || $metadata === []) {
            return false;
        }

        $fetchedAt = (int) ($metadata['fetched_at'] ?? 0);
        $ttl = (int) config('supportmanager.avatar_cache_ttl', 86400);

        return $fetchedAt + $ttl > time();
    }

    private function contentType(int $userId): string
    {
        return (string) ($this->metadata($userId)['content_type'] ?? 'application/octet-stream');
    }

    /**
     * @return array{content_type?: string, fetched_at?: int}
     */
    private function metadata(int $userId): array
    {
        if (! Storage::disk(self::DISK)->exists($this->metaPath($userId))) {
            return [];
        }

        try {
            $metadata = json_decode(Storage::disk(self::DISK)->get($this->metaPath($userId)) ?: '', true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($metadata) ? $metadata : [];
    }

    private function detectContentType(string $body, ?string $contentType): ?string
    {
        $imageInfo = @getimagesizefromstring($body);

        if (is_array($imageInfo) && isset($imageInfo['mime'])) {
            return (string) $imageInfo['mime'];
        }

        $normalized = mb_strtolower(mb_trim(explode(';', $contentType ?? '')[0]));

        return str_starts_with($normalized, 'image/') ? $normalized : null;
    }

    private function dataUri(int $userId): ?string
    {
        if (! $this->exists($userId)) {
            return null;
        }

        $contentType = $this->contentType($userId);

        if (! str_starts_with($contentType, 'image/')) {
            return null;
        }

        return 'data:'.$contentType.';base64,'.base64_encode(Storage::disk(self::DISK)->get($this->imagePath($userId)) ?: '');
    }

    private function imagePath(int $userId): string
    {
        return self::DIRECTORY.'/'.$userId.'.bin';
    }

    private function metaPath(int $userId): string
    {
        return self::DIRECTORY.'/'.$userId.'.json';
    }
}
