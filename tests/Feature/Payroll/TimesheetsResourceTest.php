<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Payroll\Timesheet;
use SupportManager\Sdk\Http\Download;

function payrollTimesheetPage(int $page, int $lastPage): array
{
    return [
        'data' => [[
            'type' => 'timesheets',
            'id' => (string) $page,
            'attributes' => [
                'user_id' => 7,
                'user' => ['id' => 7, 'name' => "Employee {$page}"],
                'process_date' => '2026-06-30T00:00:00Z',
                'status' => 'submitted',
                'has_variations' => true,
                'gross_pay' => 1800.0,
            ],
        ]],
        'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => 1, 'total' => $lastPage],
        'links' => ['next' => $page < $lastPage ? 'https://api.test/api/v1/payroll/timesheets?page='.($page + 1) : null],
    ];
}

it('encodes filter, sort and pagination as Spatie-style query params', function () {
    fakeApi(['payroll/timesheets*' => Http::response(payrollTimesheetPage(1, 1))]);

    sdk()->timesheets()->query()->filter('status', 'submitted')->sort('-process_date')->perPage(25)->get();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'filter[status]=submitted')
            && str_contains($url, 'sort=-process_date')
            && str_contains($url, 'per_page=25')
            && $request->hasHeader('X-Team-ID', '42');
    });
});

it('hydrates timesheet DTOs and exposes pagination metadata', function () {
    fakeApi(['payroll/timesheets*' => Http::response(payrollTimesheetPage(1, 2))]);

    $page = sdk()->timesheets()->query()->get();

    expect($page->items()[0])->toBeInstanceOf(Timesheet::class)
        ->and($page->items()[0]->status)->toBe('submitted')
        ->and($page->items()[0]->grossPay)->toBe(1800.0)
        ->and($page->items()[0]->hasVariations)->toBeTrue()
        ->and($page->hasMorePages())->toBeTrue();
});

it('finds a timesheet by id', function () {
    fakeApi([
        'payroll/timesheets/3' => [
            'data' => ['type' => 'timesheets', 'id' => '3', 'attributes' => [
                'status' => 'approved',
                'comments' => 'OK',
                'gross_pay' => 2000.0,
                'shift_hours_total' => '152.50',
                'sleep_count' => 12,
                'additional_payments_total' => '86.40',
            ]],
        ],
    ]);

    $timesheet = sdk()->timesheets()->find(3);

    expect($timesheet)->toBeInstanceOf(Timesheet::class)
        ->and($timesheet->id)->toBe('3')
        ->and($timesheet->status)->toBe('approved')
        ->and($timesheet->comments)->toBe('OK')
        ->and($timesheet->shiftHoursTotal)->toBe('152.50')
        ->and($timesheet->sleepCount)->toBe(12)
        ->and($timesheet->additionalPaymentsTotal)->toBe('86.40');
});

it('returns null when finding a missing timesheet (404)', function () {
    fakeApi(['payroll/timesheets/999' => Http::response(['message' => 'Not found'], 404)]);

    expect(sdk()->timesheets()->find(999))->toBeNull();
});

it('downloads a timesheet PDF as a raw response', function () {
    fakeApi(['payroll/timesheets/3/download' => Http::response('%PDF-1.4 timesheet-bytes', 200, ['Content-Type' => 'application/pdf'])]);

    $response = sdk()->timesheets()->download(3);

    expect($response)->toBeInstanceOf(Download::class)
        ->and($response->body())->toBe('%PDF-1.4 timesheet-bytes');
});
