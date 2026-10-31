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
 * Fake signature requests — documents sent to the current user to sign.
 *
 * A JSON:API collection (type `signature-requests`). The index is not
 * paginated, filters on `status` (awaiting by default, or signed) and leaves
 * out `fields`/`consent_statement`, so it is an explicit route; showing a
 * request marks it viewed. The signing flow is faked end to end: `values`
 * saves the user's own field values, `sign` checks consent and required fields
 * before completing the request, and `file`/`signed` serve the PDF and the
 * signed-copy link. The per-request `meta.can` flags live under each record's
 * reserved `meta` key and gate the write actions, as the platform's policy
 * does.
 *
 * ```php
 * SupportManager::fake()->seed(
 *     SignatureRequestFactory::new()->count(2),
 *     SignatureRequestFactory::new()->signed(),
 * );
 * SupportManager::signatureRequests()->saveValues(1, ['field-1-signature' => 'data:image/png;base64,AAA']);
 * SupportManager::signatureRequests()->sign(1, agreesToSignElectronically: true);
 * ```
 */
final class SignatureRequestFactory extends Factory implements ProvidesFakeRoutes
{
    public const string CONSENT_STATEMENT = 'I agree to sign this document electronically. I intend my electronic signature, and everything else I have filled in, to have the same effect as if I had signed a paper copy by hand.';

    public function path(): string
    {
        return 'signing/requests';
    }

    public function type(): string
    {
        return 'signature-requests';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'document' => [
                'name' => "Document {$number}",
                'status' => 'sent',
                'sent_at' => '2026-10-30T09:15:00.000000Z',
                'completed_at' => null,
            ],
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'role' => null,
            'sent_at' => '2026-10-30T09:15:00.000000Z',
            'viewed_at' => null,
            'signed_at' => null,
            'signed_via' => null,
            'fields' => [
                self::field("field-{$number}-signature", 'signature'),
                self::field("field-{$number}-checkbox", 'checkbox', required: false),
            ],
            'consent_statement' => self::CONSENT_STATEMENT,
            'meta' => ['can' => ['sign' => true, 'download_signed' => false]],
        ];
    }

    /**
     * Seed a request the user has already signed, on a completed document.
     */
    public function signed(): static
    {
        return $this->state(static fn (int $index): array => [
            'document' => [
                'name' => 'Signed document '.($index + 1),
                'status' => 'completed',
                'sent_at' => '2026-10-30T09:15:00.000000Z',
                'completed_at' => '2026-10-31T10:00:00.000000Z',
            ],
            'viewed_at' => '2026-10-31T09:55:00.000000Z',
            'signed_at' => '2026-10-31T10:00:00.000000Z',
            'signed_via' => 'app',
            'meta' => ['can' => ['sign' => false, 'download_signed' => true]],
        ]);
    }

    public function fakeRoutes(): array
    {
        return [
            // Index: unpaginated, filtered by status, without fields or consent.
            FakeRoute::get('signing/requests', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $signed = ($fake->query($request)['status'] ?? 'awaiting') === 'signed';

                $records = array_values(array_map(
                    static function (array $record): array {
                        unset($record['fields'], $record['consent_statement']);

                        return $record;
                    },
                    array_filter(
                        $store->all('signing/requests'),
                        static fn (array $record): bool => (($record['signed_at'] ?? null) !== null) === $signed,
                    ),
                ));

                return Http::response(Json::collection('signature-requests', $records));
            }),

            FakeRoute::get('signing/requests/{id}', static function (RecordStore $store, array $matches): mixed {
                $record = $store->find('signing/requests', $matches[1]);

                if ($record === null) {
                    return Http::response(['message' => 'Not Found'], 404);
                }

                if (($record['viewed_at'] ?? null) === null) {
                    $record = $store->update('signing/requests', $matches[1], ['viewed_at' => '2026-10-31T08:02:11.000000Z']) ?? $record;
                }

                return Http::response(Json::item('signature-requests', $record));
            }),

            FakeRoute::get('signing/requests/{id}/file', static function (RecordStore $store, array $matches): mixed {
                if ($store->find('signing/requests', $matches[1]) === null) {
                    return Http::response(['message' => 'Not Found'], 404);
                }

                return Http::response('%PDF-1.4 signing-bytes', 200, ['Content-Type' => 'application/pdf']);
            }),

            FakeRoute::put('signing/requests/{id}/values', static function (RecordStore $store, array $matches, Request $request): mixed {
                $record = $store->find('signing/requests', $matches[1]);

                if ($record === null) {
                    return Http::response(['message' => 'Not Found'], 404);
                }

                if (! self::can($record, 'sign')) {
                    return Http::response(['message' => 'This action is unauthorized.'], 403);
                }

                $values = $request->data();

                $store->update('signing/requests', $matches[1], [
                    'fields' => array_map(
                        static fn (array $field): array => ($field['is_mine'] ?? false) && array_key_exists((string) $field['id'], $values)
                            ? [...$field, 'value' => $values[(string) $field['id']]]
                            : $field,
                        self::fields($record),
                    ),
                ]);

                return Http::response('', 204);
            }),

            FakeRoute::post('signing/requests/{id}/sign', static function (RecordStore $store, array $matches, Request $request): mixed {
                $record = $store->find('signing/requests', $matches[1]);

                if ($record === null) {
                    return Http::response(['message' => 'Not Found'], 404);
                }

                if (! self::can($record, 'sign')) {
                    return Http::response(['message' => 'This action is unauthorized.'], 403);
                }

                if (! array_key_exists('agrees_to_sign_electronically', $request->data())) {
                    return self::invalid('agrees_to_sign_electronically', 'The agrees to sign electronically field is required.');
                }

                if ($request->data()['agrees_to_sign_electronically'] !== true) {
                    return self::invalid('consent', 'Agree to sign electronically to finish signing.');
                }

                foreach (self::fields($record) as $field) {
                    if (($field['is_mine'] ?? false) && ($field['is_required'] ?? false) && ($field['value'] ?? null) === null) {
                        return self::invalid((string) $field['id'], 'Please fill in this field.');
                    }
                }

                /** @var array<string, mixed> $document */
                $document = $record['document'] ?? [];

                $signed = $store->update('signing/requests', $matches[1], [
                    'document' => [...$document, 'status' => 'completed', 'completed_at' => '2026-10-31T10:00:00.000000Z'],
                    'signed_at' => '2026-10-31T10:00:00.000000Z',
                    'signed_via' => 'app',
                    'meta' => ['can' => ['sign' => false, 'download_signed' => true]],
                ]);

                return Http::response(Json::item('signature-requests', $signed ?? $record));
            }),

            FakeRoute::get('signing/requests/{id}/signed', static function (RecordStore $store, array $matches): mixed {
                $record = $store->find('signing/requests', $matches[1]);

                if ($record === null) {
                    return Http::response(['message' => 'Not Found'], 404);
                }

                if (! self::can($record, 'download_signed')) {
                    return Http::response(['message' => 'This action is unauthorized.'], 403);
                }

                /** @var array{name?: string} $document */
                $document = $record['document'] ?? [];

                return Http::response(Json::item(null, [
                    'download_url' => 'https://s3.test/signing/signed/'.$matches[1].'.pdf',
                    'filename' => ($document['name'] ?? 'Document').' (signed).pdf',
                    'expires_at' => '2026-10-31T10:05:00.000000Z',
                ]));
            }),
        ];
    }

    /**
     * One field entry, as a request's `fields` lists it.
     *
     * @return array<string, mixed>
     */
    private static function field(string $id, string $type, bool $required = true): array
    {
        return [
            'id' => $id,
            'type' => $type,
            'is_mine' => true,
            'is_required' => $required,
            'page' => 1,
            'x' => 0.12,
            'y' => 0.81,
            'width' => 0.3,
            'height' => 0.06,
            'value' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return list<array<string, mixed>>
     */
    private static function fields(array $record): array
    {
        /** @var list<array<string, mixed>> $fields */
        $fields = is_array($record['fields'] ?? null) ? $record['fields'] : [];

        return $fields;
    }

    /**
     * Whether a record's `meta.can` grants an ability.
     *
     * @param  array<string, mixed>  $record
     */
    private static function can(array $record, string $ability): bool
    {
        /** @var array{can?: array<string, bool>} $meta */
        $meta = $record['meta'] ?? [];

        return $meta['can'][$ability] ?? false;
    }

    /**
     * A 422 keyed by a single field, as the platform's validator emits it.
     */
    private static function invalid(string $field, string $message): mixed
    {
        return Http::response(['message' => $message, 'errors' => [$field => [$message]]], 422);
    }
}
