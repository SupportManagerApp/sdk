<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake keysafe — a flat singleton (`type()` is null, so responses are a bare
 * `{data: {...}}`, not a JSON:API document).
 *
 * It models the platform's "the code is only ever returned by reveal" rule: the
 * code is held in the seeded record but stripped from {@see show()}/{@see
 * update()} responses, and surfaced only by `reveal()`.
 *
 * ```php
 * SupportManager::fake()->seed(KeysafeFactory::new()->withCode('4321'));
 * SupportManager::keysafe()->show()->hasCode;    // true
 * SupportManager::keysafe()->reveal()->code;     // '4321'
 * ```
 */
final class KeysafeFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'household/keysafe';
    }

    public function definition(int $index): array
    {
        return [
            'has_code' => true,
            'can_edit' => true,
            'code' => '1234',
        ];
    }

    /**
     * Seed a specific stored code.
     */
    public function withCode(string $code): static
    {
        return $this->state(['has_code' => $code !== '', 'code' => $code]);
    }

    /**
     * Seed with no code set.
     */
    public function withoutCode(): static
    {
        return $this->state(['has_code' => false, 'code' => null]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::get('household/keysafe', static fn (RecordStore $store): mixed => Http::response(
                Json::item(null, self::masked($store)),
            )),

            FakeRoute::post('household/keysafe/reveal', static function (RecordStore $store): mixed {
                $record = $store->all('household/keysafe')[0] ?? ['has_code' => false, 'can_edit' => true, 'code' => null];

                return Http::response(Json::item(null, $record));
            }),

            FakeRoute::patch('household/keysafe', static function (RecordStore $store, array $matches, Request $request): mixed {
                $code = $request->data()['code'] ?? null;
                $code = is_string($code) && $code !== '' ? $code : null;

                $store->put('household/keysafe', [[
                    'has_code' => $code !== null,
                    'can_edit' => true,
                    'code' => $code,
                ]]);

                return Http::response(Json::item(null, self::masked($store)));
            }),
        ];
    }

    /**
     * The stored record with its code hidden — the shape show()/update() return.
     *
     * @return array<string, mixed>
     */
    private static function masked(RecordStore $store): array
    {
        $record = $store->all('household/keysafe')[0] ?? ['has_code' => false, 'can_edit' => true];
        unset($record['code']);

        return $record;
    }
}
