<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Dashboard\Capabilities;
use SupportManager\Sdk\Data\Dashboard\CurrentWeather;
use SupportManager\Sdk\Data\Dashboard\DailyWeather;
use SupportManager\Sdk\Data\Dashboard\DashboardAlert;
use SupportManager\Sdk\Data\Dashboard\DashboardLayout;
use SupportManager\Sdk\Data\Dashboard\DashboardQuickLink;
use SupportManager\Sdk\Data\Dashboard\DashboardWidget;
use SupportManager\Sdk\Data\Dashboard\NavigationArea;
use SupportManager\Sdk\Data\Dashboard\WeatherForecast;
use SupportManager\Sdk\Enums\Dashboard\OptionalModule;
use SupportManager\Sdk\Enums\Dashboard\PlanFeature;
use SupportManager\Sdk\Enums\Dashboard\WeatherCondition;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;

it('reads the dashboard layout from a flat {data, meta} payload', function () {
    fakeApi(['dashboard' => [
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
    ]]);

    $layout = sdk()->dashboard()->layout();

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
    fakeApi(['capabilities' => [
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
    ]]);

    $capabilities = sdk()->dashboard()->capabilities();

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

// The platform's WeatherApiTest body (WeatherController::show).
function weatherForecastBody(): array
{
    return [
        'data' => [
            'location' => 'Weymouth',
            'current' => [
                'temperature' => 14,
                'feels_like' => 11,
                'condition' => 'partly_cloudy',
                'condition_label' => 'Partly cloudy',
                'icon' => 'cloud-moon',
                'is_daytime' => false,
                'wind_speed_mph' => 12,
            ],
            'days' => [
                ['date' => '2026-10-09', 'day_name' => 'Today', 'condition' => 'drizzle', 'condition_label' => 'Drizzle', 'icon' => 'cloud-drizzle', 'high' => 18, 'low' => 14, 'rain_chance' => 60],
                ['date' => '2026-10-10', 'day_name' => 'Sat', 'condition' => 'thunderstorm', 'condition_label' => 'Thunderstorms', 'icon' => 'cloud-lightning', 'high' => 13, 'low' => 8, 'rain_chance' => 90],
            ],
        ],
        'meta' => ['team_id' => 42],
    ];
}

it('reads the weather forecast', function () {
    fakeApi(['dashboard/weather' => weatherForecastBody()]);

    $forecast = sdk()->dashboard()->weather();

    expect($forecast)->toBeInstanceOf(WeatherForecast::class)
        ->and($forecast->location)->toBe('Weymouth')
        ->and($forecast->current)->toEqual(new CurrentWeather(
            temperature: 14,
            feelsLike: 11,
            condition: WeatherCondition::PartlyCloudy,
            conditionLabel: 'Partly cloudy',
            icon: 'cloud-moon',
            isDaytime: false,
            windSpeedMph: 12,
        ))
        ->and($forecast->days)->toHaveCount(2)
        ->and($forecast->days[0])->toEqual(new DailyWeather(
            date: '2026-10-09',
            dayName: 'Today',
            condition: WeatherCondition::Drizzle,
            conditionLabel: 'Drizzle',
            icon: 'cloud-drizzle',
            high: 18,
            low: 14,
            rainChance: 60,
        ))
        ->and($forecast->days[1]->condition)->toBe(WeatherCondition::Thunderstorm)
        ->and($forecast->days[1]->condition->label())->toBe($forecast->days[1]->conditionLabel);

    assertSentTo('GET', 'dashboard/weather', fn ($request) => $request->hasHeader('X-Team-ID', '42'));
});

it('returns no forecast while the weather is unavailable', function () {
    fakeApi(['dashboard/weather' => ['data' => null, 'meta' => ['team_id' => 42]]]);

    expect(sdk()->dashboard()->weather())->toBeNull();
});

it('reads a weather condition it does not know as overcast, as the platform says to', function () {
    $body = weatherForecastBody();
    $body['data']['current']['condition'] = 'hail';
    $body['data']['days'][0]['condition'] = 'sandstorm';
    fakeApi(['dashboard/weather' => $body]);

    $forecast = sdk()->dashboard()->weather();

    expect($forecast?->current->condition)->toBe(WeatherCondition::Overcast)
        ->and($forecast?->days[0]->condition)->toBe(WeatherCondition::Overcast)
        ->and($forecast?->days[1]->condition)->toBe(WeatherCondition::Thunderstorm);
});

it('throws when a former PA asks for the weather', function () {
    fakeApi(['dashboard/weather' => Http::response(['message' => 'This action is unauthorized.'], 403)]);

    expect(fn () => sdk()->dashboard()->weather())->toThrow(
        fn (SupportManagerApiException $exception) => expect($exception->status)->toBe(403),
    );
});

it('remembers the capabilities for the session, refetching on request', function () {
    fakeApi(['capabilities' => Http::sequence()
        ->push(dashboardCapabilitiesBody('PA'))
        ->push(dashboardCapabilitiesBody('Employer'))
        ->push(dashboardCapabilitiesBody('PA')),
    ]);

    expect(sdk()->dashboard()->capabilities()->role)->toBe('PA')
        ->and(sdk()->dashboard()->capabilities()->role)->toBe('PA');
    Http::assertSentCount(1);

    expect(sdk()->dashboard()->capabilities(fresh: true)->role)->toBe('Employer')
        ->and(sdk()->dashboard()->capabilities()->role)->toBe('Employer');
    Http::assertSentCount(2);

    sdk()->session()->setCurrentTeamId(43);

    expect(sdk()->dashboard()->capabilities()->role)->toBe('PA');
    Http::assertSentCount(3);
});

function dashboardCapabilitiesBody(string $role): array
{
    return [
        'data' => [
            'role' => $role,
            'is_privileged' => $role === 'Employer',
            'is_employee' => $role !== 'Employer',
            'is_former_pa' => false,
            'subscribed' => true,
            'plan_features' => ['dashboard' => true],
            'modules' => ['household' => true],
            'navigation' => [],
        ],
        'meta' => ['team_id' => 42],
    ];
}
