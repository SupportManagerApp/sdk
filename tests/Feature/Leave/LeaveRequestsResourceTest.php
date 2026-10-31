<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Leave\LeaveRequest;
use SupportManager\Sdk\Enums\Leave\LeaveDurationType;
use SupportManager\Sdk\Enums\Leave\LeaveStatus;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Resources\Leave\LeaveRequestsResource;

function leaveRequests(): LeaveRequestsResource
{
    return app(LeaveRequestsResource::class);
}

function leaveRequestPage(int $page, int $lastPage): array
{
    return [
        'data' => [[
            'type' => 'leave-requests',
            'id' => (string) $page,
            'attributes' => [
                'user_id' => 3,
                'user' => ['id' => 3, 'name' => "User {$page}"],
                'start_date' => '2026-07-01T08:00:00Z',
                'end_date' => '2026-07-05T08:00:00Z',
                'type' => 'Whole Days',
                'status' => 'Pending',
                'hours' => 30.0,
                'sleeps' => 0.0,
            ],
        ]],
        'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => 1, 'total' => $lastPage],
        'links' => ['next' => $page < $lastPage ? 'https://api.test/api/v1/leave/requests?page='.($page + 1) : null],
    ];
}

it('encodes filter, sort and pagination as Spatie-style query params', function () {
    Http::fake(['api.test/api/v1/leave/requests*' => Http::response(leaveRequestPage(1, 1))]);
    bootTenantSession();

    leaveRequests()->query()
        ->filter('status', 'Pending')
        ->sort('-created_at')
        ->perPage(15)
        ->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[status]=Pending')
            && str_contains($url, 'sort=-created_at')
            && str_contains($url, 'per_page=15')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates DTOs and exposes pagination metadata', function () {
    Http::fake(['api.test/api/v1/leave/requests*' => Http::response(leaveRequestPage(1, 3))]);
    bootTenantSession();

    $page = leaveRequests()->query()->get();

    expect($page->items())->toHaveCount(1)
        ->and($page->items()[0])->toBeInstanceOf(LeaveRequest::class)
        ->and($page->items()[0]->userName)->toBe('User 1')
        ->and($page->items()[0]->type)->toBe(LeaveDurationType::WholeDays)
        ->and($page->items()[0]->status)->toBe(LeaveStatus::Pending)
        ->and($page->items()[0]->hours)->toBe(30.0)
        ->and($page->currentPage())->toBe(1)
        ->and($page->lastPage())->toBe(3)
        ->and($page->hasMorePages())->toBeTrue();
});

it('finds a single request by id', function () {
    Http::fake([
        'api.test/api/v1/leave/requests/7' => Http::response([
            'data' => ['type' => 'leave-requests', 'id' => '7', 'attributes' => ['status' => 'Approved', 'hours' => 8.0]],
        ]),
    ]);
    bootTenantSession();

    $request = leaveRequests()->find(7);

    expect($request)->toBeInstanceOf(LeaveRequest::class)
        ->and($request->id)->toBe('7')
        ->and($request->status)->toBe(LeaveStatus::Approved);
});

it('hydrates the leave type and the viewer permissions from the resource meta', function () {
    Http::fake([
        'api.test/api/v1/leave/requests/7' => Http::response([
            'data' => [
                'type' => 'leave-requests',
                'id' => '7',
                'attributes' => [
                    'start_date' => '2024-03-15T08:00:00+00:00',
                    'end_date' => '2024-03-22T08:00:00+00:00',
                    'leave_type' => 'annual_leave',
                    'leave_type_label' => 'Annual leave',
                    'status' => 'Approved',
                ],
                'meta' => ['can' => ['edit_dates' => false, 'cancel' => true, 'request_reapproval' => true]],
            ],
            'meta' => ['team_id' => 42],
        ]),
    ]);
    bootTenantSession();

    $request = leaveRequests()->find(7);

    expect($request->leaveType)->toBe('annual_leave')
        ->and($request->leaveTypeLabel)->toBe('Annual leave')
        ->and($request->endDate)->toBe('2024-03-22T08:00:00+00:00')
        ->and($request->canEditDates)->toBeFalse()
        ->and($request->canCancel)->toBeTrue()
        ->and($request->canRequestReapproval)->toBeTrue();
});

it('leaves the permissions null when the resource carries no meta', function () {
    Http::fake(['api.test/api/v1/leave/requests*' => Http::response(leaveRequestPage(1, 1))]);
    bootTenantSession();

    $request = leaveRequests()->all()->items()[0];

    expect($request->canEditDates)->toBeNull()
        ->and($request->canCancel)->toBeNull()
        ->and($request->leaveType)->toBeNull();
});

it('exposes the status counts from the index meta', function () {
    $body = leaveRequestPage(1, 1);
    $body['meta']['status_counts'] = ['Pending' => 1, 'Approved' => 4, 'Rejected' => 0, 'Cancelled' => 2];
    Http::fake(['api.test/api/v1/leave/requests*' => Http::response($body)]);
    bootTenantSession();

    expect(leaveRequests()->query()->filter('status', 'Pending')->get()->meta()['status_counts'])
        ->toBe(['Pending' => 1, 'Approved' => 4, 'Rejected' => 0, 'Cancelled' => 2]);
});

it('returns null when finding a missing request (404)', function () {
    Http::fake(['api.test/api/v1/leave/requests/999' => Http::response(['message' => 'Not found'], 404)]);
    bootTenantSession();

    expect(leaveRequests()->find(999))->toBeNull();
});

it('creates a leave request', function () {
    Http::fake(['api.test/api/v1/leave/requests' => Http::response([
        'data' => ['type' => 'leave-requests', 'id' => '8', 'attributes' => ['status' => 'Pending', 'hours' => 8.0]],
    ], 201)]);
    bootTenantSession();

    $request = leaveRequests()->create(['start_date' => '2026-07-01', 'end_date' => '2026-07-03', 'type' => 'Whole Days']);

    expect($request->id)->toBe('8');
    Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['type'] === 'Whole Days');
});

it('updates a leave request', function () {
    Http::fake(['api.test/api/v1/leave/requests/8' => Http::response([
        'data' => ['type' => 'leave-requests', 'id' => '8', 'attributes' => ['requester_comments' => 'Updated']],
    ])]);
    bootTenantSession();

    $request = leaveRequests()->update(8, ['requester_comments' => 'Updated']);

    expect($request->requesterComments)->toBe('Updated');
    Http::assertSent(fn ($r) => $r->method() === 'PATCH');
});

it('deletes a leave request', function () {
    Http::fake(['api.test/api/v1/leave/requests/8' => Http::response(['message' => 'deleted'])]);
    bootTenantSession();

    leaveRequests()->destroy(8);

    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), 'leave/requests/8'));
});

it('approves a request', function () {
    Http::fake(['api.test/api/v1/leave/requests/8/approve' => Http::response([
        'data' => ['type' => 'leave-requests', 'id' => '8', 'attributes' => ['status' => 'Approved']],
    ])]);
    bootTenantSession();

    expect(leaveRequests()->approve(8)->status)->toBe(LeaveStatus::Approved);
    Http::assertSent(fn ($r) => $r->method() === 'PATCH');
});

it('rejects a request with comments', function () {
    Http::fake(['api.test/api/v1/leave/requests/8/reject' => Http::response([
        'data' => ['type' => 'leave-requests', 'id' => '8', 'attributes' => ['status' => 'Rejected', 'approver_comments' => 'No']],
    ])]);
    bootTenantSession();

    $request = leaveRequests()->reject(8, 'No');

    expect($request->status)->toBe(LeaveStatus::Rejected)
        ->and($request->approverComments)->toBe('No');
    Http::assertSent(fn ($r) => $r['approver_comments'] === 'No');
});

it('cancels a request', function () {
    Http::fake(['api.test/api/v1/leave/requests/8/cancel' => Http::response([
        'data' => ['type' => 'leave-requests', 'id' => '8', 'attributes' => ['status' => 'Cancelled']],
    ])]);
    bootTenantSession();

    expect(leaveRequests()->cancel(8, 'changed plans')->status)->toBe(LeaveStatus::Cancelled);
    Http::assertSent(fn ($r) => $r['reason'] === 'changed plans');
});

it('maps a 422 to a validation exception', function () {
    Http::fake(['api.test/api/v1/leave/requests' => Http::response([
        'message' => 'The given data was invalid',
        'errors' => ['start_date' => ['The start date is required.']],
    ], 422)]);
    bootTenantSession();

    try {
        leaveRequests()->create([]);
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('start_date'))->toBe('The start date is required.');
    }
});
