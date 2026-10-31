<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Concerns;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Fake;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * The fake-route plumbing the three compliance factories share: decoding a
 * (possibly multipart) self-submission, and finding and saving the
 * authenticated user's own record. Their document downloads are
 * {@see \SupportManager\Sdk\Testing\FakeRoute::downloadLink()}s.
 *
 * The fake has no notion of "the current user", so the user's own record is
 * the one whose `meta.can.update` is true — the platform sets that flag only for
 * the record's own employee.
 */
trait FakesComplianceRecords
{
    /**
     * A submission's fields by name. Multipart file parts map to their client
     * filename (what the platform's resource then reports), text parts to
     * their contents; a JSON body is returned as-is.
     *
     * @return array<string, mixed>
     */
    private static function submittedFields(Request $request): array
    {
        if (! $request->isMultipart()) {
            return $request->data();
        }

        $fields = [];

        foreach ($request->data() as $part) {
            if (! is_array($part) || ! isset($part['name'])) {
                continue;
            }

            $fields[(string) $part['name']] = isset($part['filename'])
                ? (string) $part['filename']
                : $part['contents'] ?? null;
        }

        return $fields;
    }

    /**
     * The authenticated user's own record at a path, or null when none is seeded.
     *
     * @return array<string, mixed>|null
     */
    private static function ownRecord(RecordStore $store, string $path): ?array
    {
        foreach ($store->all($path) as $record) {
            if (($record['meta']['can']['update'] ?? false) === true) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Replace the user's own record (200) or create it (201), as the platform's
     * create-or-replace `store` does, answering with the saved record.
     *
     * @param  array<string, mixed>|null  $own
     * @param  array<string, mixed>  $attributes
     */
    private static function saveOwnRecord(RecordStore $store, Fake $fake, string $path, string $type, ?array $own, array $attributes): mixed
    {
        if ($own !== null) {
            $saved = $store->update($path, (string) $own['id'], $attributes) ?? $own;

            return Http::response(Json::item($type, $saved));
        }

        $saved = $store->append($path, [
            'id' => $store->nextId($path),
            'team_id' => $fake->teamId(),
            ...$attributes,
            'created_at' => '2026-01-01T09:00:00Z',
            'updated_at' => '2026-01-01T09:00:00Z',
            'meta' => ['can' => ['update' => true, 'download' => true]],
        ]);

        return Http::response(Json::item($type, $saved), 201);
    }
}
