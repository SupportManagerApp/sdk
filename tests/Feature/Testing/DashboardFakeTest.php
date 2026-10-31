<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\Dashboard\Capabilities;
use SupportManager\Sdk\Data\Dashboard\DashboardLayout;
use SupportManager\Sdk\Data\Dashboard\WeatherForecast;
use SupportManager\Sdk\Enums\Dashboard\OptionalModule;
use SupportManager\Sdk\Enums\Dashboard\PlanFeature;
use SupportManager\Sdk\Enums\Dashboard\WeatherCondition;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Testing\Factories\CapabilitiesFactory;
use SupportManager\Sdk\Testing\Factories\DashboardFactory;
use SupportManager\Sdk\Testing\Factories\WeatherForecastFactory;

// Dashboard endpoints are flat singletons ({data, meta}), not JSON:API.

it('reads the seeded dashboard layout', function () {
    SupportManager::fake()->seed(DashboardFactory::new()->withAlert('2 leave requests to approve', 2));

    $layout = SupportManager::dashboard()->layout();

    expect($layout)->toBeInstanceOf(DashboardLayout::class)
        ->and($layout->widgets)->toHaveCount(3)
        ->and($layout->widgets[0]->key)->toBe('dashboard.weather')
        ->and($layout->widgets[2]->isHalfWidth())->toBeTrue()
        ->and($layout->quickLinks[0]->label)->toBe('Rota')
        ->and($layout->alerts[0]->description)->toBe('2 leave requests to approve')
        ->and($layout->alerts[0]->count)->toBe(2);
});

it('seeds a chosen set of widgets in order', function () {
    SupportManager::fake()->seed(DashboardFactory::new()->withWidgets('rota.today-rota', 'tasks.getting-started'));

    $widgets = SupportManager::dashboard()->layout()->widgets;

    expect(array_map(static fn ($widget): string => $widget->key, $widgets))->toBe(['rota.today-rota', 'tasks.getting-started'])
        ->and($widgets[1]->priority)->toBe(20);
});

it('reads the seeded capabilities', function () {
    SupportManager::fake()->seed(CapabilitiesFactory::new());

    $capabilities = SupportManager::dashboard()->capabilities();

    expect($capabilities)->toBeInstanceOf(Capabilities::class)
        ->and($capabilities->role)->toBe('PA')
        ->and($capabilities->subscribed)->toBeTrue()
        ->and($capabilities->hasPlanFeature(PlanFeature::ESigning))->toBeTrue()
        ->and($capabilities->usesModule(OptionalModule::Household))->toBeTrue()
        ->and($capabilities->usesModule(OptionalModule::Accidents))->toBeFalse()
        ->and($capabilities->navigation[0]->items[0]->routeName)->toBe('payroll.index');
});

it('seeds a manager on an unsubscribed team with chosen modules', function () {
    SupportManager::fake()->seed(
        CapabilitiesFactory::new()->manager()->unsubscribed()->withModules(OptionalModule::Accidents, OptionalModule::ESigning),
    );

    $capabilities = SupportManager::dashboard()->capabilities();

    expect($capabilities->role)->toBe('Manager')
        ->and($capabilities->isPrivileged)->toBeTrue()
        ->and($capabilities->subscribed)->toBeFalse()
        ->and($capabilities->usesModule(OptionalModule::Accidents))->toBeTrue()
        ->and($capabilities->usesModule(OptionalModule::ESigning))->toBeTrue()
        ->and($capabilities->usesModule(OptionalModule::Household))->toBeFalse();
});

it('seeds a former PA', function () {
    SupportManager::fake()->seed(CapabilitiesFactory::new()->formerPa());

    $capabilities = SupportManager::dashboard()->capabilities();

    expect($capabilities->isFormerPa)->toBeTrue()
        ->and($capabilities->isEmployee)->toBeFalse();
});

it('drives a 403 on the dashboard through the real error mapping', function () {
    SupportManager::fake()
        ->seed(DashboardFactory::new())
        ->fail('dashboard', 403, ['message' => 'Subscription required.']);

    SupportManager::dashboard()->layout();
})->throws(SupportManagerApiException::class);

it('reads the seeded weather forecast beside the layout', function () {
    SupportManager::fake()->seed(DashboardFactory::new(), WeatherForecastFactory::new());

    $forecast = SupportManager::dashboard()->weather();

    expect($forecast)->toBeInstanceOf(WeatherForecast::class)
        ->and($forecast?->location)->toBe('Weymouth')
        ->and($forecast?->current->condition)->toBe(WeatherCondition::PartlyCloudy)
        ->and($forecast?->current->temperature)->toBe(14)
        ->and($forecast?->days)->toHaveCount(2)
        ->and($forecast?->days[1]->condition)->toBe(WeatherCondition::Thunderstorm)
        ->and(SupportManager::dashboard()->layout()->widgets[0]->key)->toBe('dashboard.weather');
});

it('seeds a forecast that is unavailable', function () {
    SupportManager::fake()->seed(WeatherForecastFactory::new()->unavailable());

    expect(SupportManager::dashboard()->weather())->toBeNull();
});

it('drives the 403 a former PA gets for the weather', function () {
    SupportManager::fake()
        ->seed(WeatherForecastFactory::new())
        ->fail('dashboard/weather', 403, ['message' => 'This action is unauthorized.']);

    SupportManager::dashboard()->weather();
})->throws(SupportManagerApiException::class, 'This action is unauthorized.');
