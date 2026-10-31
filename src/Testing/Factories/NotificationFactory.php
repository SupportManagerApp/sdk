<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\Fake;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake database notifications.
 *
 * The body is an ordinary JSON:API collection, but the index takes a flat
 * `filter=unread|read` (not Spatie's `filter[key]=value`) and reports an
 * `unread_count` in its meta — so the index, the read/unread toggles, and the
 * bulk actions are all explicit routes here rather than generic CRUD.
 *
 * Ids are UUIDs, as for any Laravel database notification; read them back from
 * the index (`all()->items()[0]->id`) or seed a fixed one with
 * `->state(['id' => '…'])`. The mutating routes answer the platform's
 * `{success, message}` body, or a 404 `{message: 'Notification not found.'}`
 * for an unknown id (NotificationController).
 *
 * ```php
 * SupportManager::fake()->seed(
 *     NotificationFactory::new()->count(3),       // unread
 *     NotificationFactory::new()->read()->count(2),
 * );
 * SupportManager::notifications()->unreadCount(); // 3
 * ```
 */
final class NotificationFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'notifications';
    }

    public function type(): string
    {
        return 'notifications';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'id' => (string) Str::uuid(),
            // `type` here is the notification's PHP class, which overrides the
            // JSON:API resource type once flattened — exactly as the platform
            // serialises it.
            'type' => 'App\\Notifications\\GenericNotification',
            'data' => ['message' => "Notification {$number}"],
            'read_at' => null,
            'created_at' => '2026-01-01T09:00:00Z',
        ];
    }

    /**
     * Mark the notification(s) as already read.
     */
    public function read(): static
    {
        return $this->state(['read_at' => '2026-01-01T10:00:00Z']);
    }

    public function fakeRoutes(): array
    {
        return [
            // Index: flat filter + unread_count meta.
            FakeRoute::get('notifications', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $all = $store->all('notifications');
                $filter = $fake->query($request)['filter'] ?? null;

                $records = match ($filter) {
                    'unread' => array_values(array_filter($all, static fn (array $r): bool => ($r['read_at'] ?? null) === null)),
                    'read' => array_values(array_filter($all, static fn (array $r): bool => ($r['read_at'] ?? null) !== null)),
                    default => $all,
                };

                $unread = count(array_filter($all, static fn (array $r): bool => ($r['read_at'] ?? null) === null));

                return $fake->paginate('notifications', 'notifications', $records, $request, ['unread_count' => $unread]);
            }),

            FakeRoute::patch('notifications/{id}/read', static fn (RecordStore $store, array $matches): mixed => $store->update('notifications', $matches[1], ['read_at' => '2026-01-01T10:00:00Z']) === null
                ? Json::notFound('Notification not found.')
                : Http::response(['success' => true, 'message' => 'Notification marked as read.'])),

            FakeRoute::patch('notifications/{id}/unread', static fn (RecordStore $store, array $matches): mixed => $store->update('notifications', $matches[1], ['read_at' => null]) === null
                ? Json::notFound('Notification not found.')
                : Http::response(['success' => true, 'message' => 'Notification marked as unread.'])),

            FakeRoute::delete('notifications/{id}', static fn (RecordStore $store, array $matches): mixed => $store->delete('notifications', $matches[1])
                ? Http::response(['success' => true, 'message' => 'Notification deleted.'])
                : Json::notFound('Notification not found.')),

            FakeRoute::post('notifications/read-all', static function (RecordStore $store): mixed {
                foreach ($store->all('notifications') as $record) {
                    $store->update('notifications', (string) $record['id'], ['read_at' => '2026-01-01T10:00:00Z']);
                }

                return Http::response(['success' => true, 'message' => 'All notifications marked as read.']);
            }),

            FakeRoute::delete('notifications', static function (RecordStore $store): mixed {
                $store->truncate('notifications');

                return Http::response(['success' => true, 'message' => 'All notifications deleted.']);
            }),
        ];
    }
}
