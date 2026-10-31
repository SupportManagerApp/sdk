<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Concerns\FakesComplianceRecords;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake DBS (Disclosure and Barring Service) checks.
 *
 * JSON:API collection (`dbs-checks`), so generic GET list/find come for free
 * from the resource path. This factory adds the employee's multipart
 * {@see \SupportManager\Sdk\Resources\Compliance\DBSChecksResource::submit()}
 * and the flat certificate download. States cover the obvious outcomes
 * ({@see clear()}/{@see flagged()}), a stored certificate
 * ({@see withCertificate()}), and the authenticated user's own record
 * ({@see own()}).
 *
 * The fake has no notion of "the current user", so it treats the record whose
 * `meta.can.update` is true — as the platform sets only for the record's own
 * employee — as the user's own: `submit()` replaces that record (200), or
 * creates one (201) when none is seeded. Like the platform, a submission
 * without a certificate is a 422 unless one is already stored.
 *
 * ```php
 * SupportManager::fake()->seed(DBSCheckFactory::new()->count(3));
 * SupportManager::dbsChecks()->find(2);
 * SupportManager::dbsChecks()->submit('2026-03-14', '001234567890', 'PDF-BYTES', 'dbs.pdf');
 * ```
 */
final class DBSCheckFactory extends Factory implements ProvidesFakeRoutes
{
    use FakesComplianceRecords;

    public function path(): string
    {
        return 'compliance/dbs-checks';
    }

    public function type(): string
    {
        return 'dbs-checks';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'team_id' => 42,
            'user_id' => $number,
            'user' => ['id' => $number, 'name' => "Employee {$number}"],
            'date_of_issue' => '2026-01-01T00:00:00Z',
            'certificate_number' => "CERT-{$number}",
            'certificate' => null,
            'check_result' => 'clear',
            'notes' => null,
            'created_at' => '2026-01-01T09:00:00Z',
            'updated_at' => '2026-01-01T10:00:00Z',
            'meta' => ['can' => ['update' => false, 'download' => true]],
        ];
    }

    /**
     * A clear (no records found) check result.
     */
    public function clear(): static
    {
        return $this->state(['check_result' => 'clear']);
    }

    /**
     * A check that surfaced records to review.
     */
    public function flagged(): static
    {
        return $this->state(['check_result' => 'flagged']);
    }

    /**
     * A check with an uploaded certificate, so the download route answers.
     */
    public function withCertificate(string $filename = 'certificate.pdf'): static
    {
        return $this->state(['certificate' => $filename]);
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
            FakeRoute::post('compliance/dbs-checks', static function (RecordStore $store, array $matches, Request $request): mixed {
                $fields = self::submittedFields($request);
                $own = self::ownRecord($store, 'compliance/dbs-checks');
                $certificate = $fields['certificate'] ?? $own['certificate'] ?? null;

                if ($certificate === null || $certificate === '') {
                    return Http::response([
                        'message' => 'Please upload a copy of your DBS certificate.',
                        'errors' => ['certificate' => ['Please upload a copy of your DBS certificate.']],
                    ], 422);
                }

                return self::saveOwnRecord($store, 'compliance/dbs-checks', 'dbs-checks', $own, [
                    // The platform stores the date and serialises it as midnight
                    // wall-clock time in Europe/London (ApiResource::wallClock()).
                    'date_of_issue' => isset($fields['date_of_issue'])
                        ? (new DateTimeImmutable((string) $fields['date_of_issue'], new DateTimeZone('Europe/London')))->format(DATE_ATOM)
                        : null,
                    'certificate_number' => $fields['certificate_number'] ?? null,
                    'certificate' => $certificate,
                ]);
            }),

            FakeRoute::get('compliance/dbs-checks/{id}/certificate', static fn (RecordStore $store, array $matches): mixed => self::downloadPayload(
                $store->find('compliance/dbs-checks', $matches[1])['certificate'] ?? null,
                "dbs-checks/{$matches[1]}",
            )),
        ];
    }
}
