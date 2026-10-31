<?php

declare(strict_types=1);

use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Testing\Factories\LeaveRequestFactory;
use SupportManager\Sdk\Testing\Factories\PolicyFactory;
use SupportManager\Sdk\Testing\Factories\ShiftFactory;
use SupportManager\Sdk\Testing\Factories\TaskFactory;

// The fake's tenant is whatever team it is acting as: seeded records, records
// created through it, and every meta.team_id follow actingAsTeam().

it('reports the default team without actingAsTeam()', function () {
    SupportManager::fake()->seed(TaskFactory::new(), LeaveRequestFactory::new());

    expect(SupportManager::tasks()->all()->meta()['team_id'])->toBe(42)
        ->and(SupportManager::leaveRequests()->find(1)->teamId)->toBe(42);
});

it('seeds records into the team the fake is acting as', function () {
    SupportManager::fake()->actingAsTeam(7)->seed(
        LeaveRequestFactory::new(),
        PolicyFactory::new(),
        ShiftFactory::new(),
    );

    expect(SupportManager::leaveRequests()->find(1)->teamId)->toBe(7)
        ->and(SupportManager::policies()->find(1)->teamId)->toBe(7)
        ->and(SupportManager::shifts()->find(1)->teamId)->toBe(7);
});

it('echoes the current team in meta.team_id', function () {
    SupportManager::fake()->seed(TaskFactory::new());
    SupportManager::fake()->actingAsTeam(9);

    expect(SupportManager::tasks()->all()->meta()['team_id'])->toBe(9);
});

it('stamps records created through the fake with the current team', function () {
    SupportManager::fake()->actingAsTeam(7)->seed(ShiftFactory::new()->count(0));

    $shift = SupportManager::shifts()->create(['assignee_id' => 3, 'date' => '2026-08-17', 'start_time' => '09:00', 'end_time' => '17:00']);

    expect($shift->teamId)->toBe(7);
});

it('keeps a factory pinned to another team with forTeam()', function () {
    SupportManager::fake()->actingAsTeam(7)->seed(LeaveRequestFactory::new()->forTeam(3));

    expect(SupportManager::leaveRequests()->find(1)->teamId)->toBe(3);
});
