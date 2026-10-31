<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Unit\Testing\Fixtures;

use SupportManager\Sdk\Testing\Factory;

/**
 * A minimal host-style factory for a tenant-scoped resource, whose records
 * take their `team_id` from the fake.
 */
final class WidgetFactory extends Factory
{
    public function path(): string
    {
        return 'widgets';
    }

    public function type(): string
    {
        return 'widgets';
    }

    public function definition(int $index): array
    {
        return [
            'team_id' => $this->teamId(),
            'name' => 'Widget '.($index + 1),
            'colour' => $index % 2 === 0 ? 'red' : 'blue',
        ];
    }
}
