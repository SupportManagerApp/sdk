<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Http\Client\Request;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Testing\Fake;
use SupportManager\Sdk\Tests\Unit\Testing\Fixtures\WidgetFactory;

it('acts as the default team until told otherwise', function () {
    $fake = SupportManager::fake();

    expect($fake->teamId())->toBe(Fake::DEFAULT_TEAM_ID)
        ->and($fake->actingAsTeam(7)->teamId())->toBe(7);
});

it('seeds records into the team the fake is acting as', function () {
    $fake = SupportManager::fake();
    $fake->seed(WidgetFactory::new());
    $fake->actingAsTeam(7)->seed(WidgetFactory::new()->count(2));

    expect(array_column($fake->store()->all('widgets'), 'team_id'))->toBe([42, 7, 7]);
});

it('keeps the team a factory names with forTeam()', function () {
    $fake = SupportManager::fake()->actingAsTeam(7);
    $fake->seed(WidgetFactory::new()->forTeam(9), WidgetFactory::new());

    expect(array_column($fake->store()->all('widgets'), 'team_id'))->toBe([9, 7]);
});

it('builds records for the default team outside the fake', function () {
    expect(WidgetFactory::new()->makeOne()['team_id'])->toBe(Fake::DEFAULT_TEAM_ID)
        ->and(WidgetFactory::new()->forTeam(3)->makeOne()['team_id'])->toBe(3)
        ->and(WidgetFactory::new()->forTeam(3)->withDefaultTeam(8)->makeOne()['team_id'])->toBe(3)
        ->and(WidgetFactory::new()->withDefaultTeam(8)->makeOne()['team_id'])->toBe(8);
});

it('filters records by exact filter[key]=value equality', function () {
    $fake = SupportManager::fake()->seed(WidgetFactory::new()->count(3));
    $request = new Request(new PsrRequest('GET', 'https://api.test/api/v1/widgets?filter[colour]=red&filter[team_id]=42'));

    expect(array_column($fake->filter($fake->store()->all('widgets'), $request), 'name'))->toBe(['Widget 1', 'Widget 3'])
        ->and($fake->filter($fake->store()->all('widgets'), new Request(new PsrRequest('GET', 'https://api.test/api/v1/widgets'))))->toHaveCount(3);
});
