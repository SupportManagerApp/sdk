<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Dashboard\Capabilities;
use SupportManager\Sdk\Data\Dashboard\DashboardAlert;
use SupportManager\Sdk\Data\Dashboard\DashboardLayout;
use SupportManager\Sdk\Data\Dashboard\DashboardQuickLink;
use SupportManager\Sdk\Data\Dashboard\DashboardWidget;
use SupportManager\Sdk\Data\Dashboard\NavigationArea;
use SupportManager\Sdk\Enums\Dashboard\OptionalModule;
use SupportManager\Sdk\Enums\Dashboard\PlanFeature;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Resources\Dashboard\DashboardResource;

function dashboardResource(): DashboardResource
{
    return app(DashboardResource::class);
}

it('reads the dashboard layout from a flat {data, meta} payload', function () {
    Http::fake(['api.test/api/v1/dashboard' => Http::response([
        'data' => [
            'widgets' => [
                ['key' => 'dashboard.weather', 'width' => 'full', 'priority' => 10],
                ['key' => 'leave.leave-summary', 'width' => 'half', 'priority' => 210],
            ],
            'quick_links' => [
                ['label' => 'Rota', 'description' => 'Check the team schedule', 'icon' => 'heroicon-o-user-group', 'color' => 'orange', 'url' => 'https://suptmgr.test/acme-care/rota'],
            ],
            'alerts' => [
                ['description' => '2 leave requests to approve', 'count' => 2, 'location' => 'Time off › Approvals', 'icon' => 'plane', 'url' => 'https://suptmgr.test/acme-care/leave-admin/approvals'],
            ],
        ],
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    $layout = dashboardResource()->layout();

    expect($layout)->toBeInstanceOf(DashboardLayout::class)
        ->and($layout->widgets)->toHaveCount(2)
        ->and($layout->widgets[0])->toBeInstanceOf(DashboardWidget::class)
        ->and($layout->widgets[0]->key)->toBe('dashboard.weather')
        ->and($layout->widgets[1]->isHalfWidth())->toBeTrue()
        ->and($layout->widgets[1]->priority)->toBe(210)
        ->and($layout->quickLinks[0])->toBeInstanceOf(DashboardQuickLink::class)
        ->and($layout->quickLinks[0]->url)->toBe('https://suptmgr.test/acme-care/rota')
        ->and($layout->alerts[0])->toBeInstanceOf(DashboardAlert::class)
        ->and($layout->alerts[0]->count)->toBe(2)
        ->and($layout->alerts[0]->location)->toBe('Time off › Approvals');

    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && str_ends_with($request->url(), 'api/v1/dashboard')
        && $request->hasHeader('X-Team-ID', '42'));
});

it('reads the user capabilities', function () {
    Http::fake(['api.test/api/v1/capabilities' => Http::response([
        'data' => [
            'role' => 'PA',
            'is_privileged' => false,
            'is_employee' => true,
            'is_former_pa' => false,
            'subscribed' => true,
            'plan_features' => ['dashboard' => true, 'payroll' => false, 'something_new' => true],
            'modules' => ['household' => true, 'accidents' => false],
            'navigation' => [
                ['key' => 'pay', 'label' => 'Pay', 'icon' => 'banknotes', 'items' => [
                    ['label' => 'Payslips', 'icon' => 'banknotes', 'route_name' => 'payroll.index'],
                    ['label' => 'Unknown', 'icon' => 'question', 'route_name' => null],
                ]],
            ],
        ],
        'meta' => ['team_id' => 42],
    ])]);
    bootTenantSession();

    $capabilities = dashboardResource()->capabilities();

    expect($capabilities)->toBeInstanceOf(Capabilities::class)
        ->and($capabilities->role)->toBe('PA')
        ->and($capabilities->isEmployee)->toBeTrue()
        ->and($capabilities->subscribed)->toBeTrue()
        ->and($capabilities->hasPlanFeature(PlanFeature::Dashboard))->toBeTrue()
        ->and($capabilities->hasPlanFeature(PlanFeature::Payroll))->toBeFalse()
        ->and($capabilities->hasPlanFeature(PlanFeature::Rota))->toBeFalse()
        ->and($capabilities->hasPlanFeature('something_new'))->toBeTrue()
        ->and($capabilities->usesModule(OptionalModule::Household))->toBeTrue()
        ->and($capabilities->usesModule(OptionalModule::Accidents))->toBeFalse()
        ->and($capabilities->navigation)->toHaveCount(1)
        ->and($capabilities->navigation[0])->toBeInstanceOf(NavigationArea::class)
        ->and($capabilities->navigation[0]->items[0]->routeName)->toBe('payroll.index')
        ->and($capabilities->navigation[0]->items[1]->routeName)->toBeNull();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), 'api/v1/capabilities')
        && $request->hasHeader('X-Team-ID', '42'));
});

it('throws when the dashboard is refused for an unsubscribed team (403)', function () {
    Http::fake(['api.test/api/v1/dashboard' => Http::response(['message' => 'Subscription required.'], 403)]);
    bootTenantSession();

    dashboardResource()->layout();
})->throws(SupportManagerApiException::class);
