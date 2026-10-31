<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\Fake;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake push-notification device tokens.
 *
 * The collection is a JSON:API resource (type `device_tokens`) at
 * `push-notifications/tokens`; register and destroy-by-id are served by generic
 * CRUD. This factory adds the module's quirks (DeviceTokensController): the
 * index is an unpaginated collection carrying `meta.count`; the FLAT
 * `status()` endpoint (a bare `{data: {...}}`, not a JSON:API document);
 * forget (a DELETE on the bare path carrying a `token` in the body) and
 * destroyAll (DELETE `/all`, which generic REST would otherwise read as a
 * delete of the id "all") answer `{success, message}`; and
 * `push-notifications/prompt/dismiss` answers `{success, message}` and flips
 * the status's `prompt_dismissed`.
 *
 * ```php
 * SupportManager::fake()->seed(DeviceTokenFactory::new()->count(2));
 * SupportManager::deviceTokens()->status()->hasTokens; // true
 * ```
 */
final class DeviceTokenFactory extends Factory implements ProvidesFakeRoutes
{
    /**
     * Where the fake remembers that the setup prompt was dismissed.
     */
    private const string PROMPT_PATH = 'push-notifications/prompt';

    public function path(): string
    {
        return 'push-notifications/tokens';
    }

    public function type(): string
    {
        return 'device_tokens';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'token' => "fcm-token-{$number}",
            'platform' => 'ios',
            'last_used_at' => '2026-06-06T08:00:00+00:00',
            'created_at' => '2026-06-01T08:00:00+00:00',
            'updated_at' => '2026-06-06T08:00:00+00:00',
        ];
    }

    /**
     * Seed an Android token.
     */
    public function android(): static
    {
        return $this->state(['platform' => 'android']);
    }

    /**
     * Seed a web token.
     */
    public function web(): static
    {
        return $this->state(['platform' => 'web']);
    }

    public function fakeRoutes(): array
    {
        return [
            // GET tokens — every token, unpaginated, with a `meta.count`.
            FakeRoute::get('push-notifications/tokens', static function (RecordStore $store): mixed {
                $tokens = $store->all('push-notifications/tokens');

                return Http::response(Json::collection('device_tokens', $tokens, ['count' => count($tokens)]));
            }),

            // Flat status payload, derived from the seeded tokens.
            FakeRoute::get('push-notifications/tokens/status', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $count = count($store->all('push-notifications/tokens'));

                return Http::response(Json::item(null, [
                    'has_tokens' => $count > 0,
                    'token_count' => $count,
                    'prompt_dismissed' => $store->all(self::PROMPT_PATH) !== [],
                ]));
            }),

            // Delete every token for the current user — must precede the generic
            // DELETE /{id} so "all" is not treated as an id.
            FakeRoute::delete('push-notifications/tokens/all', static function (RecordStore $store): mixed {
                $count = count($store->all('push-notifications/tokens'));
                $store->truncate('push-notifications/tokens');

                return Http::response([
                    'success' => true,
                    'message' => 'All tokens removed.',
                    'meta' => ['removed_count' => $count],
                ]);
            }),

            // Forget by value: a DELETE on the bare path with a `token` body.
            FakeRoute::delete('push-notifications/tokens', static function (RecordStore $store, array $matches, Request $request): mixed {
                $token = $request->data()['token'] ?? null;

                foreach ($store->all('push-notifications/tokens') as $record) {
                    if (($record['token'] ?? null) === $token) {
                        $store->delete('push-notifications/tokens', (string) $record['id']);
                    }
                }

                return Http::response(['success' => true, 'message' => 'Token removed.']);
            }),

            // Dismiss the in-app setup prompt (idempotent).
            FakeRoute::post('push-notifications/prompt/dismiss', static function (RecordStore $store): mixed {
                $store->put(self::PROMPT_PATH, [['id' => '1', 'dismissed' => true]]);

                return Http::response(['success' => true, 'message' => 'Notification prompt dismissed.']);
            }),
        ];
    }
}
