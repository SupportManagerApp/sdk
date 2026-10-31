<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Notifications\Notification;

function notificationId(int $n): string
{
    return sprintf('9d8f1a2b-1c3d-4e5f-8a9b-%012d', $n);
}

function notificationPage(int $page, int $lastPage, int $unread = 2): array
{
    return [
        'data' => [[
            'type' => 'notifications',
            'id' => notificationId($page),
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
    fakeApi(['notifications*' => Http::response(notificationPage(1, 3))]);

    $page = sdk()->notifications()->all();

    expect($page->items())->toHaveCount(1)
        ->and($page->items()[0])->toBeInstanceOf(Notification::class)
        ->and($page->items()[0]->id)->toBe(notificationId(1))
        ->and($page->items()[0]->type)->toBe('SupportManager\\Leave\\Notifications\\LeaveStatusUpdatedNotification')
        ->and($page->items()[0]->data['message'])->toBe('Notice 1')
        ->and($page->items()[0]->isUnread())->toBeTrue()
        ->and($page->meta()['unread_count'])->toBe(2)
        ->and($page->hasMorePages())->toBeTrue();

    Http::assertSent(fn ($r) => $r->method() === 'GET' && $r->hasHeader('X-Team-ID', '42'));
});

it('filters to unread notifications with a flat filter param', function () {
    fakeApi(['notifications*' => Http::response(notificationPage(1, 1))]);

    sdk()->notifications()->unread(25);

    Http::assertSent(function ($r) {
        $url = urldecode($r->url());

        return str_contains($url, 'filter=unread') && str_contains($url, 'per_page=25');
    });
});

it('filters to read notifications with a flat filter param', function () {
    fakeApi(['notifications*' => Http::response(notificationPage(1, 1))]);

    sdk()->notifications()->read();

    Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'filter=read'));
});

it('walks to the next page', function () {
    fakeApi([
        'notifications?page=2*' => Http::response(notificationPage(2, 2)),
        'notifications*' => Http::response(notificationPage(1, 2)),
    ]);

    $next = sdk()->notifications()->all()->next();

    expect($next)->not->toBeNull()
        ->and($next->currentPage())->toBe(2)
        ->and($next->items()[0]->id)->toBe(notificationId(2));
});

it('reads the unread count from the index meta', function () {
    fakeApi(['notifications*' => Http::response(notificationPage(1, 1, unread: 5))]);

    expect(sdk()->notifications()->unreadCount())->toBe(5);
    Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'per_page=1'));
});

it('marks a notification as read', function () {
    fakeApi(['notifications/'.notificationId(1).'/read' => ['success' => true, 'message' => 'Notification marked as read.']]);

    sdk()->notifications()->markAsRead(notificationId(1));

    Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_contains($r->url(), 'notifications/'.notificationId(1).'/read'));
});

it('marks a notification as unread', function () {
    fakeApi(['notifications/'.notificationId(1).'/unread' => ['success' => true, 'message' => 'Notification marked as unread.']]);

    sdk()->notifications()->markAsUnread(notificationId(1));

    Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_contains($r->url(), 'notifications/'.notificationId(1).'/unread'));
});

it('deletes a notification', function () {
    fakeApi(['notifications/'.notificationId(1) => ['success' => true, 'message' => 'Notification deleted.']]);

    sdk()->notifications()->destroy(notificationId(1));

    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), 'notifications/'.notificationId(1)));
});

it('marks all notifications as read', function () {
    fakeApi(['notifications/read-all' => ['success' => true]]);

    sdk()->notifications()->markAllAsRead();

    Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), 'notifications/read-all'));
});

it('deletes all notifications', function () {
    fakeApi(['notifications' => ['success' => true]]);

    sdk()->notifications()->destroyAll();

    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/notifications'));
});
