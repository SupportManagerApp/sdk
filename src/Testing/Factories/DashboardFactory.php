<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use SupportManager\Sdk\Testing\Factory;

/**
 * Fake dashboard layout — a flat singleton (`{data: {widgets, quick_links,
 * alerts}}`), so `type()` stays null and the generic singleton read serves it.
 *
 * ```php
 * SupportManager::fake()->seed(DashboardFactory::new()->withAlert('2 leave requests to approve', 2));
 * SupportManager::dashboard()->layout()->alerts;
 * ```
 */
final class DashboardFactory extends Factory
{
    public function path(): string
    {
        return 'dashboard';
    }

    public function definition(int $index): array
    {
        return [
            'widgets' => [
                ['key' => 'dashboard.weather', 'width' => 'full', 'priority' => 10],
                ['key' => 'rota.today-rota', 'width' => 'full', 'priority' => 100],
                ['key' => 'leave.leave-summary', 'width' => 'half', 'priority' => 210],
            ],
            'quick_links' => [
                [
                    'label' => 'Rota',
                    'description' => 'Check the team schedule',
                    'icon' => 'heroicon-o-user-group',
                    'color' => 'orange',
                    'url' => 'https://suptmgr.test/acme-care/rota',
                ],
            ],
            'alerts' => [],
        ];
    }

    /**
     * Show only these widget keys, in order, at full width.
     */
    public function withWidgets(string ...$keys): static
    {
        $widgets = [];

        foreach (array_values($keys) as $position => $key) {
            $widgets[] = ['key' => $key, 'width' => 'full', 'priority' => ($position + 1) * 10];
        }

        return $this->state(['widgets' => $widgets]);
    }

    /**
     * Add a "needs your attention" alert.
     */
    public function withAlert(string $description, int $count = 1): static
    {
        return $this->state([
            'alerts' => [[
                'description' => $description,
                'count' => $count,
                'location' => 'Time off › Approvals',
                'icon' => 'plane',
                'url' => 'https://suptmgr.test/acme-care/leave-admin/approvals',
            ]],
        ]);
    }
}
