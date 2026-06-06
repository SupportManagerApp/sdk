<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Notifications\Notification;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Resources\Notifications\NotificationsResource;

function notifications(): NotificationsResource
{
    return app(NotificationsResource::class);
}

function notificationPage(int $page, int $lastPage, int $unread = 2): array
{
    return [
        'data' => [[
            'type' => 'notifications',
            'id' => "uuid-{$page}",
            'attributes' => [
                'type' => 'SupportManager\\Leave\\Notifications\\LeaveStatusUpdatedNotification',
                'data' => ['message' => "Notice {$page}", 'leave_request_id' => $page],
                'read_at' => null,
                'created_at' => '2026-06-06T08:00:00+00:00',
            ],
        ]],
        'meta' => [
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => 1,
            'total' => $lastPage,
            'unread_count' => $unread,
        ],
        'links' => ['next' => $page < $lastPage ? 'https://api.test/api/v1/notifications?page='.($page + 1) : null],
    ];
}

it('lists notifications, hydrating DTOs and exposing the unread count', function () {
    Http::fake(['api.test/api/v1/notifications*' => Http::response(notificationPage(1, 3))]);
    bootTenantSession();

    $page = notifications()->all();

    expect($page->items())->toHaveCount(1)
        ->and($page->items()[0])->toBeInstanceOf(Notification::class)
        ->and($page->items()[0]->id)->toBe('uuid-1')
        ->and($page->items()[0]->type)->toBe('SupportManager\\Leave\\Notifications\\LeaveStatusUpdatedNotification')
        ->and($page->items()[0]->data['message'])->toBe('Notice 1')
        ->and($page->items()[0]->isUnread())->toBeTrue()
        ->and($page->meta()['unread_count'])->toBe(2)
        ->and($page->hasMorePages())->toBeTrue();

    Http::assertSent(fn ($r) => $r->method() === 'GET' && $r->hasHeader('X-Team-ID', '42'));
});

it('filters to unread notifications with a flat filter param', function () {
    Http::fake(['api.test/api/v1/notifications*' => Http::response(notificationPage(1, 1))]);
    bootTenantSession();

    notifications()->unread(25);

    Http::assertSent(function ($r) {
        $url = urldecode($r->url());

        return str_contains($url, 'filter=unread') && str_contains($url, 'per_page=25');
    });
});

it('filters to read notifications with a flat filter param', function () {
    Http::fake(['api.test/api/v1/notifications*' => Http::response(notificationPage(1, 1))]);
    bootTenantSession();

    notifications()->read();

    Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'filter=read'));
});

it('walks to the next page', function () {
    Http::fake([
        'api.test/api/v1/notifications?page=2*' => Http::response(notificationPage(2, 2)),
        'api.test/api/v1/notifications*' => Http::response(notificationPage(1, 2)),
    ]);
    bootTenantSession();

    $next = notifications()->all()->next();

    expect($next)->not->toBeNull()
        ->and($next->currentPage())->toBe(2)
        ->and($next->items()[0]->id)->toBe('uuid-2');
});

it('reads the unread count from the index meta', function () {
    Http::fake(['api.test/api/v1/notifications*' => Http::response(notificationPage(1, 1, unread: 5))]);
    bootTenantSession();

    expect(notifications()->unreadCount())->toBe(5);
    Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'per_page=1'));
});

it('marks a notification as read', function () {
    Http::fake(['api.test/api/v1/notifications/uuid-1/read' => Http::response(['success' => true])]);
    bootTenantSession();

    notifications()->markAsRead('uuid-1');

    Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_contains($r->url(), 'notifications/uuid-1/read'));
});

it('marks a notification as unread', function () {
    Http::fake(['api.test/api/v1/notifications/uuid-1/unread' => Http::response(['success' => true])]);
    bootTenantSession();

    notifications()->markAsUnread('uuid-1');

    Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_contains($r->url(), 'notifications/uuid-1/unread'));
});

it('deletes a notification', function () {
    Http::fake(['api.test/api/v1/notifications/uuid-1' => Http::response(['success' => true])]);
    bootTenantSession();

    notifications()->destroy('uuid-1');

    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), 'notifications/uuid-1'));
});

it('marks all notifications as read', function () {
    Http::fake(['api.test/api/v1/notifications/read-all' => Http::response(['success' => true])]);
    bootTenantSession();

    notifications()->markAllAsRead();

    Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), 'notifications/read-all'));
});

it('deletes all notifications', function () {
    Http::fake(['api.test/api/v1/notifications' => Http::response(['success' => true])]);
    bootTenantSession();

    notifications()->destroyAll();

    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/notifications'));
});

it('throws when marking a missing notification as read (404)', function () {
    Http::fake(['api.test/api/v1/notifications/missing/read' => Http::response(['message' => 'Notification not found.'], 404)]);
    bootTenantSession();

    notifications()->markAsRead('missing');
})->throws(SupportManagerApiException::class);
