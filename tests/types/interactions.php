<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Types;

use SupportManager\Sdk\Client\SupportManagerClient;
use SupportManager\Sdk\Data\CarJourneys\CarJourney;
use SupportManager\Sdk\Facades\SupportManager as SupportManagerFacade;
use SupportManager\Sdk\Resources\CarJourneys\CarJourneysResource;
use SupportManager\Sdk\Session\AuthSession;
use SupportManager\Sdk\Session\CurrentTeam;
use SupportManager\Sdk\SupportManager;

use function PHPStan\Testing\assertType;

/**
 * Not executed — analysed by PHPStan to prove the whole interaction chain keeps
 * its concrete types end to end, which is what drives IDE autocomplete. If a
 * link in the chain ever decays to a base type, `composer analyse` fails here.
 */
function probe_manager(SupportManager $sm, CarJourneysResource $journeys): void
{
    // Service accessors stay concrete.
    assertType(SupportManagerClient::class, $sm->auth());
    assertType(AuthSession::class, $sm->session());
    assertType(CurrentTeam::class, $sm->team());

    // The typed resource accessor preserves the concrete resource class.
    assertType(CarJourneysResource::class, $sm->resource(CarJourneysResource::class));

    // Query → page → items, all carrying the DTO type.
    $query = $journeys->query();
    assertType('SupportManager\Sdk\Http\ResourceQuery<'.CarJourney::class.'>', $query);
    assertType('SupportManager\Sdk\Http\Paginated<'.CarJourney::class.'>', $query->get());
    assertType('list<'.CarJourney::class.'>', $query->get()->items());
    assertType(CarJourney::class.'|null', $query->get()->next()?->items()[0] ?? null);

    // Terminal helpers.
    assertType(CarJourney::class.'|null', $query->first());
    assertType(CarJourney::class.'|null', $journeys->find(1));
    assertType('SupportManager\Sdk\Http\Paginated<'.CarJourney::class.'>', $journeys->all());
    assertType('Illuminate\Support\Collection<int, '.CarJourney::class.'>', $query->collect());
    assertType('Illuminate\Support\LazyCollection<int, '.CarJourney::class.'>', $query->lazy());

    // Domain method on the concrete resource autocompletes and is typed.
    assertType(CarJourney::class.'|null', $journeys->ongoing());
}

function probe_facade(): void
{
    assertType(SupportManagerClient::class, SupportManagerFacade::auth());
    assertType(CurrentTeam::class, SupportManagerFacade::team());

    // The generic @method tag flows the concrete type through the facade too.
    assertType(CarJourneysResource::class, SupportManagerFacade::resource(CarJourneysResource::class));

    // Each first-party module accessor's @method tag resolves to its concrete
    // resource class (a representative spread across the modules), so a regression
    // in any of these tags fails CI here rather than silently degrading autocomplete.
    assertType(CarJourneysResource::class, SupportManagerFacade::journeys());
    assertType(\SupportManager\Sdk\Resources\Compliance\DBSChecksResource::class, SupportManagerFacade::dbsChecks());
    assertType(\SupportManager\Sdk\Resources\Documents\PoliciesResource::class, SupportManagerFacade::policies());
    assertType(\SupportManager\Sdk\Resources\Profile\ProfileResource::class, SupportManagerFacade::profile());
    assertType(\SupportManager\Sdk\Resources\Household\MealPlanResource::class, SupportManagerFacade::mealPlan());
    assertType(\SupportManager\Sdk\Resources\Leave\LeaveRequestsResource::class, SupportManagerFacade::leaveRequests());
    assertType(\SupportManager\Sdk\Resources\Rota\ShiftsResource::class, SupportManagerFacade::shifts());
    assertType(\SupportManager\Sdk\Resources\Payroll\PayslipsResource::class, SupportManagerFacade::payslips());
    assertType(\SupportManager\Sdk\Resources\Notifications\NotificationsResource::class, SupportManagerFacade::notifications());
    assertType(\SupportManager\Sdk\Resources\PushNotifications\DeviceTokensResource::class, SupportManagerFacade::deviceTokens());
    assertType(\SupportManager\Sdk\Resources\Teams\TeamsResource::class, SupportManagerFacade::teams());
    assertType(\SupportManager\Sdk\Resources\Tasks\TasksResource::class, SupportManagerFacade::tasks());
}
