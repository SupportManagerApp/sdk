<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Concerns\FakesComplianceRecords;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake driving licence verification checks.
 *
 * JSON:API collection (`drivers_licence_checks`), so generic GET list/find come
 * for free from the resource path. This factory adds the employee's
 * {@see \SupportManager\Sdk\Resources\Compliance\DriversLicenceChecksResource::submit()}
 * and the flat check-result document download. States cover the declaration
 * toggle ({@see declarationAccepted()}/{@see declarationPending()}), a stored
 * result document ({@see withCheckResultDocument()}), and the authenticated
 * user's own check ({@see own()}).
 *
 * As with {@see DBSCheckFactory}, `submit()` replaces the check flagged
 * `meta.can.update` (200) or creates one (201); like the platform, it 422s
 * unless the declaration is accepted.
 *
 * ```php
 * SupportManager::fake()->seed(DriversLicenceCheckFactory::new()->count(3));
 * SupportManager::driversLicenceChecks()->find(2);
 * SupportManager::driversLicenceChecks()->submit('HARBO801234AB9CD', 'Xy7Kp2Qm', declarationAccepted: true);
 * ```
 */
final class DriversLicenceCheckFactory extends Factory implements ProvidesFakeRoutes
{
    use FakesComplianceRecords;

    public function path(): string
    {
        return 'compliance/drivers-licence-checks';
    }

    public function type(): string
    {
        return 'drivers_licence_checks';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'team_id' => 42,
            'user_id' => $number,
            'user' => ['id' => $number, 'name' => "Employee {$number}"],
            'drivers_licence_number' => "DL-{$number}",
            'check_code' => "CHK-{$number}",
            'declaration_accepted' => true,
            'check_result' => 'clean',
            'check_result_document' => null,
            'notes' => null,
            'created_at' => '2026-01-01T09:00:00Z',
            'updated_at' => '2026-01-01T10:00:00Z',
            'meta' => ['can' => ['update' => false, 'download' => true]],
        ];
    }

    /**
     * The employee has accepted the driving record declaration.
     */
    public function declarationAccepted(): static
    {
        return $this->state(['declaration_accepted' => true]);
    }

    /**
     * The employee has not yet accepted the declaration.
     */
    public function declarationPending(): static
    {
        return $this->state(['declaration_accepted' => false]);
    }

    /**
     * A check with an uploaded result document, so the download route answers.
     */
    public function withCheckResultDocument(string $filename = 'result.pdf'): static
    {
        return $this->state(['check_result_document' => $filename]);
    }

    /**
     * The authenticated user's own check — the one `submit()` replaces.
     */
    public function own(): static
    {
        return $this->state(['meta' => ['can' => ['update' => true, 'download' => true]]]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::post('compliance/drivers-licence-checks', static function (RecordStore $store, array $matches, Request $request): mixed {
                $fields = self::submittedFields($request);

                if (($fields['declaration_accepted'] ?? false) !== true) {
                    return Http::response([
                        'message' => 'You must accept the declaration to continue.',
                        'errors' => ['declaration_accepted' => ['You must accept the declaration to continue.']],
                    ], 422);
                }

                return self::saveOwnRecord($store, 'compliance/drivers-licence-checks', 'drivers_licence_checks', self::ownRecord($store, 'compliance/drivers-licence-checks'), [
                    'drivers_licence_number' => $fields['drivers_licence_number'] ?? null,
                    'check_code' => $fields['check_code'] ?? null,
                    'declaration_accepted' => true,
                ]);
            }),

            FakeRoute::get('compliance/drivers-licence-checks/{id}/check-result-document', static fn (RecordStore $store, array $matches): mixed => self::downloadPayload(
                $store->find('compliance/drivers-licence-checks', $matches[1])['check_result_document'] ?? null,
                "drivers-licence-checks/{$matches[1]}",
            )),
        ];
    }
}
