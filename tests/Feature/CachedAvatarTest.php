<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use SupportManager\Sdk\Avatar\CachedAvatar;

function tinyPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=') ?: '';
}

function avatarDataUri(string $bytes, string $contentType = 'image/png'): string
{
    return 'data:'.$contentType.';base64,'.base64_encode($bytes);
}

it('downloads avatar bytes and returns an inline data uri', function () {
    Storage::fake('local');

    Http::fake([
        'https://cdn.test/avatar.png' => Http::response(tinyPng(), 200, ['Content-Type' => 'image/png']),
    ]);

    $url = app(CachedAvatar::class)->remember(7, 'https://cdn.test/avatar.png');

    expect($url)->toBe(avatarDataUri(tinyPng()));

    Storage::disk('local')->assertExists('auth/avatars/7.bin');
    Storage::disk('local')->assertExists('auth/avatars/7.json');

    expect(Storage::disk('local')->get('auth/avatars/7.bin'))->toBe(tinyPng());

    Http::assertSent(fn ($request) => $request->url() === 'https://cdn.test/avatar.png');
});

it('accepts image bytes when a signed url returns a generic content type', function () {
    Storage::fake('local');

    Http::fake([
        'https://cdn.test/avatar' => Http::response(tinyPng(), 200, ['Content-Type' => 'application/octet-stream']),
    ]);

    $url = app(CachedAvatar::class)->remember(7, 'https://cdn.test/avatar');

    expect($url)->toBe(avatarDataUri(tinyPng()));

    $metadata = json_decode(Storage::disk('local')->get('auth/avatars/7.json') ?: '', true, flags: JSON_THROW_ON_ERROR);

    expect($metadata['content_type'])->toBe('image/png');
});

it('reuses a fresh cached avatar without downloading it again', function () {
    Storage::fake('local');
    config()->set('supportmanager.avatar_cache_ttl', 86400);

    Storage::disk('local')->put('auth/avatars/7.bin', tinyPng());
    Storage::disk('local')->put('auth/avatars/7.json', json_encode([
        'content_type' => 'image/png',
        'fetched_at' => time(),
    ], JSON_THROW_ON_ERROR));

    Http::fake();

    $url = app(CachedAvatar::class)->remember(7, 'https://cdn.test/avatar.png');

    expect($url)->toBe(avatarDataUri(tinyPng()));
    Http::assertNothingSent();
});

it('keeps using an existing cached avatar when a refresh download fails', function () {
    Storage::fake('local');
    config()->set('supportmanager.avatar_cache_ttl', 1);

    Storage::disk('local')->put('auth/avatars/7.bin', tinyPng());
    Storage::disk('local')->put('auth/avatars/7.json', json_encode([
        'content_type' => 'image/png',
        'fetched_at' => time() - 10,
    ], JSON_THROW_ON_ERROR));

    Http::fake([
        'https://cdn.test/avatar.png' => Http::response('Not found', 404),
    ]);

    $url = app(CachedAvatar::class)->remember(7, 'https://cdn.test/avatar.png');

    expect($url)->toBe(avatarDataUri(tinyPng()))
        ->and(Storage::disk('local')->get('auth/avatars/7.bin'))->toBe(tinyPng());
});

it('exposes cached bytes and content type for host apps to serve', function () {
    Storage::fake('local');

    Storage::disk('local')->put('auth/avatars/7.bin', tinyPng());
    Storage::disk('local')->put('auth/avatars/7.json', json_encode([
        'content_type' => 'image/png',
        'fetched_at' => time(),
    ], JSON_THROW_ON_ERROR));

    $payload = app(CachedAvatar::class)->payload(7);

    expect($payload)->toBe(['body' => tinyPng(), 'content_type' => 'image/png'])
        ->and(app(CachedAvatar::class)->payload(999))->toBeNull();
});
