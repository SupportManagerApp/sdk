<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Notifications;

use SupportManager\Sdk\Data\Notifications\Notification;
use SupportManager\Sdk\Http\Document;
use SupportManager\Sdk\Http\Paginated;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * The authenticated user's database notifications.
 *
 * `all()`/`unread()`/`read()` page through the list (each carrying an
 * `unread_count` in its {@see Paginated::meta()}); `markAsRead()`/
 * `markAsUnread()` toggle a single notification; and `destroy()` removes one.
 * `markAllAsRead()`/`destroyAll()` act on the whole collection.
 *
 * Unlike the Spatie-backed collections, the index takes a single flat
 * `filter=unread|read` parameter rather than `filter[key]=value`, so the
 * filtered reads are explicit methods instead of the fluent {@see query()}.
 *
 * ```php
 * SupportManager::notifications()->unread()->items();
 * SupportManager::notifications()->markAsRead($id);
 * SupportManager::notifications()->destroyAll();
 * ```
 *
 * @extends resource<Notification>
 */
final class NotificationsResource extends Resource
{
    /**
     * The first page of notifications, newest first.
     *
     * @return Paginated<Notification>
     */
    public function all(?int $perPage = null): Paginated
    {
        return $this->list([], $perPage);
    }

    /**
     * The first page of unread notifications.
     *
     * @return Paginated<Notification>
     */
    public function unread(?int $perPage = null): Paginated
    {
        return $this->list(['filter' => 'unread'], $perPage);
    }

    /**
     * The first page of already-read notifications.
     *
     * @return Paginated<Notification>
     */
    public function read(?int $perPage = null): Paginated
    {
        return $this->list(['filter' => 'read'], $perPage);
    }

    /**
     * The number of unread notifications, read from the index `meta`.
     */
    public function unreadCount(): int
    {
        return (int) ($this->list([], 1)->meta()['unread_count'] ?? 0);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(string $id): void
    {
        $this->throwForErrorResponse($this->patch("{$this->path()}/{$id}/read"), $this->path());
    }

    /**
     * Mark a single notification as unread.
     */
    public function markAsUnread(string $id): void
    {
        $this->throwForErrorResponse($this->patch("{$this->path()}/{$id}/unread"), $this->path());
    }

    /**
     * Permanently delete a single notification.
     */
    public function destroy(string $id): void
    {
        $this->throwForErrorResponse($this->delete("{$this->path()}/{$id}"), $this->path());
    }

    /**
     * Mark every notification as read.
     */
    public function markAllAsRead(): void
    {
        $this->throwForErrorResponse($this->post("{$this->path()}/read-all"), $this->path());
    }

    /**
     * Permanently delete every notification.
     */
    public function destroyAll(): void
    {
        $this->throwForErrorResponse($this->delete($this->path()), $this->path());
    }

    protected function path(): string
    {
        return 'notifications';
    }

    protected function hydrate(ResourceObject $object): Notification
    {
        return Notification::fromArray($object->flat());
    }

    /**
     * Fetch a page of notifications with the given query, wiring up
     * {@see Paginated::next()} so callers can walk the rest.
     *
     * @param  array<string, mixed>  $query
     * @return Paginated<Notification>
     */
    private function list(array $query, ?int $perPage): Paginated
    {
        if ($perPage !== null) {
            $query['per_page'] = $perPage;
        }

        $response = $this->get($this->path(), $query);
        $this->throwForErrorResponse($response, $this->path());

        $document = Document::fromArray($response->json() ?? []);

        return new Paginated(
            items: array_map($this->hydrate(...), $document->resources()),
            meta: $document->meta(),
            links: $document->links(),
            pageFetcher: fn (int $page): Paginated => $this->list([...$query, 'page' => $page], $perPage),
        );
    }
}
