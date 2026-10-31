<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Http\Client\Request;
use SupportManager\Sdk\Enums\Compliance\PrimaryDocumentType;
use SupportManager\Sdk\Testing\Concerns\FakesComplianceRecords;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\Fake;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake right-to-work records.
 *
 * JSON:API collection (`right_to_works`, Laravel's default type for the
 * platform's `RightToWorkResource`), so generic GET list/find come for free
 * from the resource path. This factory adds the employee's multipart
 * {@see \SupportManager\Sdk\Resources\Compliance\RightToWorkResource::submit()}
 * and the flat primary/supporting document downloads. States cover a stored
 * document pair ({@see withDocuments()}) and the authenticated user's own
 * record ({@see own()}).
 *
 * As with {@see DBSCheckFactory}, `submit()` replaces the record flagged
 * `meta.can.update` (200) or creates one (201), and applies the platform's
 * type-dependent rules: a primary document unless one is stored, a share code
 * for `Immigration Share Code`, a supporting document for the birth or adoption
 * certificates (and none kept for any other type).
 *
 * ```php
 * SupportManager::fake()->seed(RightToWorkFactory::new()->count(3));
 * SupportManager::rightToWork()->find(2);
 * SupportManager::rightToWork()->submit(PrimaryDocumentType::BritishPassport, 'PDF-BYTES', 'passport.pdf');
 * ```
 */
final class RightToWorkFactory extends Factory implements ProvidesFakeRoutes
{
    use FakesComplianceRecords;

    public function path(): string
    {
        return 'compliance/right-to-work';
    }

    public function type(): string
    {
        return 'right_to_works';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'team_id' => $this->teamId(),
            'user_id' => $number,
            'user' => ['id' => $number, 'name' => "Employee {$number}"],
            'primary_document_type' => PrimaryDocumentType::BritishPassport->value,
            'primary_document' => null,
            'supporting_document' => null,
            'share_code' => "SHARE-{$number}",
            'created_at' => '2026-01-01T09:00:00Z',
            'updated_at' => '2026-01-01T10:00:00Z',
            'meta' => ['can' => ['update' => false, 'download' => true]],
        ];
    }

    /**
     * An immigration share code record (e.g. a biometric residence permit
     * holder evidencing status through the government share code service).
     */
    public function biometricResidencePermit(): static
    {
        return $this->state(['primary_document_type' => PrimaryDocumentType::ImmigrationShareCode->value]);
    }

    /**
     * A record with uploaded primary (and optionally supporting) documents, so
     * the download routes answer.
     */
    public function withDocuments(string $primary = 'primary.pdf', ?string $supporting = 'supporting.pdf'): static
    {
        return $this->state(['primary_document' => $primary, 'supporting_document' => $supporting]);
    }

    /**
     * The authenticated user's own record — the one `submit()` replaces.
     */
    public function own(): static
    {
        return $this->state(['meta' => ['can' => ['update' => true, 'download' => true]]]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::post('compliance/right-to-work', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $fields = self::submittedFields($request);
                $own = self::ownRecord($store, 'compliance/right-to-work');
                $type = PrimaryDocumentType::tryFrom((string) ($fields['primary_document_type'] ?? ''));
                $primary = $fields['primary_document'] ?? $own['primary_document'] ?? null;
                $supporting = $fields['supporting_document'] ?? $own['supporting_document'] ?? null;

                $errors = array_filter([
                    'primary_document_type' => ($fields['primary_document_type'] ?? '') === '' ? 'The primary document type field is required.' : null,
                    'primary_document' => $primary === null ? 'Please upload a copy of your primary document.' : null,
                    'share_code' => $type?->requiresShareCode() && ($fields['share_code'] ?? '') === '' ? 'The share code field is required.' : null,
                    'supporting_document' => $type?->requiresSupportingDocument() && $supporting === null
                        ? 'Please upload a supporting document (e.g. a letter showing your National Insurance number).'
                        : null,
                ]);

                if ($errors !== []) {
                    return Json::validation($errors);
                }

                return self::saveOwnRecord($store, $fake, 'compliance/right-to-work', 'right_to_works', $own, [
                    'primary_document_type' => $fields['primary_document_type'],
                    'primary_document' => $primary,
                    'supporting_document' => $type?->requiresSupportingDocument() ? $supporting : null,
                    'share_code' => $type?->requiresShareCode() ? $fields['share_code'] : null,
                ]);
            }),

            FakeRoute::downloadLink('compliance/right-to-work/{id}/primary-document', 'primary_document'),
            FakeRoute::downloadLink('compliance/right-to-work/{id}/supporting-document', 'supporting_document'),
        ];
    }
}
