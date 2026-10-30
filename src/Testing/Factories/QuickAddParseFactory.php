<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake quick-add parses — the scripted outcomes the fake parser hands back.
 *
 * Each seeded record is one parse outcome, consumed in order: `parse()` claims
 * the next unclaimed record (recording the submitted `text`/`timezone` on it)
 * and returns its token (minted then, unless the record sets its own `token`),
 * and `poll()` reads that token's {@see pending()},
 * {@see failed()} or (by default) completed state. Unknown or unclaimed tokens
 * 404, so `poll()` returns null. With nothing left to claim, `parse()` still
 * returns a token, which polls as failed — the platform's "fall back to the
 * form" answer — so a host's polling loop never hangs. Like the platform, the
 * bodies are flat `{data: {...}}`, not JSON:API.
 *
 * ```php
 * SupportManager::fake()->seed(QuickAddParseFactory::new()->shift());
 *
 * $parse = SupportManager::quickAdd()->parse('Sarah 9-5 Monday', 'Europe/London');
 * SupportManager::quickAdd()->poll($parse->token)->result->assigneeId; // 12
 * ```
 */
final class QuickAddParseFactory extends Factory implements ProvidesFakeRoutes
{
    /**
     * The platform's hint for a parse that didn't come back.
     */
    public const string FAILURE_HINT = "We couldn't read that. Try again, or enter the details yourself.";

    public function path(): string
    {
        return 'rota/quick-add/parse';
    }

    public function definition(int $index): array
    {
        return [
            'token' => null,
            'status' => 'completed',
            'claimed' => false,
            'text' => null,
            'timezone' => null,
            'hint' => null,
            'result' => self::result([
                'intent' => 'appointment',
                'title' => 'Dentist',
                'date' => '2026-08-18',
                'start_time' => '15:30',
                'duration_minutes' => 30,
                'location' => '12 High Street',
                'confidence' => 0.95,
            ]),
        ];
    }

    /**
     * A completed parse of a shift ("Sarah 9-5 Monday, tag training"), with the
     * assignee resolved.
     */
    public function shift(): static
    {
        return $this->state(['result' => self::result([
            'intent' => 'shift',
            'assignee_name' => 'Sarah',
            'assignee_id' => 12,
            'date' => '2026-08-17',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'tags' => ['training'],
            'confidence' => 0.95,
        ])]);
    }

    /**
     * A parse whose job has not published yet, so every poll reads pending.
     */
    public function pending(): static
    {
        return $this->state(['status' => 'pending', 'result' => null]);
    }

    /**
     * A parse the provider couldn't answer.
     */
    public function failed(string $hint = self::FAILURE_HINT): static
    {
        return $this->state(['status' => 'failed', 'result' => null, 'hint' => $hint]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::post('rota/quick-add/parse', static function (RecordStore $store, array $matches, Request $request): mixed {
                $input = $request->data();
                $text = is_string($input['text'] ?? null) ? $input['text'] : '';

                if (mb_strlen($text) < 2) {
                    return Http::response([
                        'message' => 'The information provided was invalid.',
                        'errors' => ['text' => ['Some text to parse is required.']],
                    ], 422);
                }

                $records = $store->all('rota/quick-add/parse');
                $claim = [
                    'claimed' => true,
                    'text' => $text,
                    'timezone' => $input['timezone'] ?? null,
                ];

                $claimed = null;

                foreach ($records as $position => $record) {
                    if (! ($record['claimed'] ?? false)) {
                        // Minted on claim from the record's position, so tokens
                        // stay unique across separate seed() calls.
                        $records[$position] = $claimed = [
                            ...$record,
                            ...$claim,
                            'token' => $record['token'] ?? self::token($position + 1),
                        ];

                        break;
                    }
                }

                if ($claimed === null) {
                    $records[] = $claimed = [
                        'token' => self::token(count($records) + 1),
                        'status' => 'failed',
                        'result' => null,
                        'hint' => self::FAILURE_HINT,
                        ...$claim,
                    ];
                }

                $store->put('rota/quick-add/parse', $records);

                return Http::response(['data' => [
                    'token' => $claimed['token'],
                    'status' => 'pending',
                    'expires_in' => 300,
                ]], 202);
            }),

            FakeRoute::get('rota/quick-add/parse/{token}', static function (RecordStore $store, array $matches): mixed {
                foreach ($store->all('rota/quick-add/parse') as $record) {
                    if (($record['claimed'] ?? false) && ($record['token'] ?? null) === $matches[1]) {
                        return Http::response(['data' => match ($record['status'] ?? null) {
                            'pending' => ['status' => 'pending'],
                            'completed' => ['status' => 'completed', 'result' => $record['result'] ?? null],
                            default => ['status' => 'failed', 'hint' => $record['hint'] ?? self::FAILURE_HINT],
                        }]);
                    }
                }

                return Http::response(['message' => 'Parse token not found.'], 404);
            }),
        ];
    }

    /**
     * A deterministic UUID-shaped token for the nth parse.
     */
    private static function token(int $number): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $number);
    }

    /**
     * A full result payload — every field present, as the platform sends it.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function result(array $overrides): array
    {
        return [
            'intent' => 'appointment',
            'title' => null,
            'assignee_name' => null,
            'assignee_id' => null,
            'assignee_candidates' => [],
            'date' => null,
            'end_date' => null,
            'start_time' => null,
            'end_time' => null,
            'duration_minutes' => null,
            'location' => null,
            'is_virtual' => false,
            'is_all_day' => false,
            'is_sleeping_night' => false,
            'is_private' => false,
            'tags' => [],
            'notes' => null,
            'confidence' => null,
            ...$overrides,
        ];
    }
}
