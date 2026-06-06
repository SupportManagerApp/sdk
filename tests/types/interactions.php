<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Types;

use SupportManager\Sdk\Client\SupportManagerClient;
use SupportManager\Sdk\Facades\SupportManager as SupportManagerFacade;
use SupportManager\Sdk\Session\AuthSession;
use SupportManager\Sdk\Session\CurrentTeam;
use SupportManager\Sdk\SupportManager;
use SupportManager\Sdk\Tests\Fixtures\CarJourneys\CarJourney;
use SupportManager\Sdk\Tests\Fixtures\CarJourneys\CarJourneysResource;

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
}
