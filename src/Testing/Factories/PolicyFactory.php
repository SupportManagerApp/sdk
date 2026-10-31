<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Enums\Documents\PolicyCategory;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake company policies.
 *
 * JSON:API collection (`policies`), so generic CRUD comes for free from the
 * resource path. This factory adds the non-RESTful `acknowledge()` action,
 * which records the current user's read receipt (so later reads report
 * `acknowledgedAt`, and re-acknowledging keeps the first receipt with a 200, as
 * the platform does) and returns a flat `{data: {policy_id, acknowledged_at}}`
 * body. States cover the category ({@see category()}), a superseded policy
 * ({@see inactive()}) and one already read ({@see acknowledged()}).
 *
 * ```php
 * SupportManager::fake()->seed(PolicyFactory::new()->count(3));
 * SupportManager::policies()->acknowledge(1);
 * ```
 */
final class PolicyFactory extends Factory implements ProvidesFakeRoutes
{
    public function path(): string
    {
        return 'documents/policies';
    }

    public function type(): string
    {
        return 'policies';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'title' => "Policy {$number}",
            'slug' => "policy-{$number}",
            'content_html' => '<p>Policy content here...</p>',
            'category' => PolicyCategory::HealthAndSafety->value,
            'category_label' => 'Health & Safety',
            'version' => '2.1',
            'effective_date' => '2024-01-01',
            'review_date' => '2025-01-01',
            'is_active' => true,
            'acknowledged_at' => null,
            'team_id' => 42,
            'created_by' => ['id' => 1, 'name' => 'Grace'],
            'created_at' => '2026-01-01T09:00:00Z',
            'updated_at' => '2026-01-01T09:00:00Z',
        ];
    }

    /**
     * Place the policy in a specific category, with a matching label.
     */
    public function category(PolicyCategory $category, string $label): static
    {
        return $this->state([
            'category' => $category->value,
            'category_label' => $label,
        ]);
    }

    /**
     * Mark the policy as inactive (superseded).
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * Mark the policy as already acknowledged by the current user.
     */
    public function acknowledged(string $at = '2026-01-01T09:00:00Z'): static
    {
        return $this->state(['acknowledged_at' => $at]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::post('documents/policies/{id}/acknowledge', static function (RecordStore $store, array $matches): mixed {
                $record = $store->find('documents/policies', $matches[1]);

                if ($record === null) {
                    return Http::response(['message' => 'Not found'], 404);
                }

                $alreadyAcknowledged = isset($record['acknowledged_at']);
                $acknowledgedAt = $alreadyAcknowledged ? (string) $record['acknowledged_at'] : '2026-01-01T09:00:00Z';
                $store->update('documents/policies', $matches[1], ['acknowledged_at' => $acknowledgedAt]);

                return Http::response([
                    'message' => $alreadyAcknowledged
                        ? 'You have already acknowledged this policy.'
                        : 'Policy acknowledged successfully.',
                    ...Json::item(null, [
                        'policy_id' => (int) $matches[1],
                        'acknowledged_at' => $acknowledgedAt,
                    ]),
                ], $alreadyAcknowledged ? 200 : 201);
            }),
        ];
    }
}
