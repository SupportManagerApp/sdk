<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Enums\Accidents\AccidentSubjectCapacity;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\Fake;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\Json;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake accident book reports.
 *
 * A JSON:API collection (type `accident_reports`, which is what the platform
 * actually emits — the OpenAPI doc says `accident-reports`), so list and find
 * come for free. This factory replaces generic create with one that shapes the
 * request into a stored report, as the platform does, and adds the
 * `subject-declaration` action with its two 422 refusals. The per-report
 * `meta.can` flags live under each record's reserved `meta` key.
 *
 * By default the reporter had the accident themselves, so the report is
 * already confirmed; use {@see awaitingSubjectDeclaration()} for one the
 * injured person still has to confirm.
 *
 * ```php
 * SupportManager::fake()->seed(AccidentReportFactory::new()->awaitingSubjectDeclaration());
 * SupportManager::accidents()->recordSubjectDeclaration(1, 'Jamie Doe');
 * ```
 */
final class AccidentReportFactory extends Factory implements ProvidesFakeRoutes
{
    /** The faked signed-in user, who records every report created through the fake. */
    private const array REPORTER = ['user_id' => 4, 'name' => 'Sam Carer'];

    public function path(): string
    {
        return 'accidents';
    }

    public function type(): string
    {
        return 'accident_reports';
    }

    public function definition(int $index): array
    {
        $number = $index + 1;

        return [
            'report_number' => $number,
            'riddor_reportable' => false,
            'subject' => [...self::REPORTER, 'occupation' => null],
            'reporter' => self::REPORTER,
            'happened_at' => '2026-10-30T14:15:00+00:00',
            'location' => 'Kitchen',
            'how_it_happened' => "Accident {$number}",
            'cause' => null,
            'injury_details' => null,
            'reporter_declaration' => ['name' => 'Sam Carer', 'declared_at' => '2026-10-30T14:20:00.000000Z'],
            'subject_declaration' => ['name' => 'Sam Carer', 'declared_at' => '2026-10-30T14:20:00.000000Z'],
            'created_at' => '2026-10-30T14:20:00.000000Z',
            'updated_at' => '2026-10-30T14:20:00.000000Z',
            'meta' => ['can' => ['record_subject_declaration' => false]],
        ];
    }

    /**
     * Seed a report about another team member that they have not confirmed yet.
     */
    public function awaitingSubjectDeclaration(int $userId = 12, string $name = 'Jamie Doe'): static
    {
        return $this->state([
            'subject' => ['user_id' => $userId, 'name' => $name, 'occupation' => null],
            'subject_declaration' => ['name' => null, 'declared_at' => null],
            'meta' => ['can' => ['record_subject_declaration' => true]],
        ]);
    }

    /**
     * Seed a report marked RIDDOR-reportable, with the outcome admins and
     * managers see.
     */
    public function riddorReportable(): static
    {
        return $this->state([
            'riddor_reportable' => true,
            'riddor' => [
                'how_reported' => 'Submitted through the HSE online form.',
                'declared_name' => 'Morgan Manager',
                'declared_at' => '2026-10-31T09:30:00.000000Z',
            ],
        ]);
    }

    public function fakeRoutes(): array
    {
        return [
            FakeRoute::post('accidents', static function (RecordStore $store, array $matches, Request $request): mixed {
                // Read the encoded body rather than data(), which still holds any
                // backed enums the caller passed un-encoded.
                /** @var array<string, mixed> $payload */
                $payload = json_decode($request->body(), true) ?? [];

                $record = [
                    ...self::reportFrom($payload),
                    'id' => $store->nextId('accidents'),
                    'report_number' => count($store->all('accidents')) + 1,
                ];
                $store->append('accidents', $record);

                return Http::response(Json::item('accident_reports', $record), 201);
            }),

            FakeRoute::post('accidents/{id}/subject-declaration', static function (RecordStore $store, array $matches, Request $request, Fake $fake): mixed {
                $record = $store->find('accidents', $matches[1]);

                if ($record === null) {
                    return Json::notFound('Not Found');
                }

                /** @var array{user_id?: int|null} $subject */
                $subject = $record['subject'] ?? [];

                if (($subject['user_id'] ?? null) !== null && $subject['user_id'] === self::REPORTER['user_id']) {
                    return self::unprocessable('The person who had the accident recorded it, so their declaration is already on the report.', $fake);
                }

                /** @var array{declared_at?: string|null} $declaration */
                $declaration = $record['subject_declaration'] ?? [];

                if (($declaration['declared_at'] ?? null) !== null) {
                    return self::unprocessable('The person who had the accident has already confirmed this report.', $fake);
                }

                $name = mb_trim((string) ($request->data()['subject_declared_name'] ?? ''));

                if ($name === '') {
                    return Json::validation(['subject_declared_name' => 'The subject declared name field is required.']);
                }

                $updated = $store->update('accidents', $matches[1], [
                    'subject_declaration' => ['name' => $name, 'declared_at' => '2026-10-31T09:00:00.000000Z'],
                    'meta' => ['can' => ['record_subject_declaration' => false]],
                ]);

                return Http::response(Json::item('accident_reports', $updated ?? $record));
            }),
        ];
    }

    /**
     * Shape a create request into the stored report, the way the platform's
     * `AccidentReportDetails` does for the faked signed-in reporter.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function reportFrom(array $payload): array
    {
        /** @var array{type?: string, user_id?: int, name?: string, capacity?: string} $subject */
        $subject = is_array($payload['subject'] ?? null) ? $payload['subject'] : [];
        $type = $subject['type'] ?? 'me';
        $reporterDeclaredName = (string) ($payload['reporter_declared_name'] ?? '');
        $subjectDeclaredName = $type === 'me' ? $reporterDeclaredName : ($payload['subject_declared_name'] ?? null);

        return [
            'riddor_reportable' => false,
            'subject' => match ($type) {
                'team_member' => ['user_id' => (int) ($subject['user_id'] ?? 0), 'name' => 'Team member '.($subject['user_id'] ?? 0), 'occupation' => null],
                'other' => ['user_id' => null, 'name' => (string) ($subject['name'] ?? ''), 'occupation' => (string) ($subject['capacity'] ?? AccidentSubjectCapacity::Other->value)],
                default => [...self::REPORTER, 'occupation' => null],
            },
            'reporter' => self::REPORTER,
            'happened_at' => $payload['happened_at'] ?? null,
            'location' => (string) ($payload['location'] ?? ''),
            'how_it_happened' => (string) ($payload['how_it_happened'] ?? ''),
            'cause' => $payload['cause'] ?? null,
            'injury_details' => $payload['injury_details'] ?? null,
            'reporter_declaration' => ['name' => $reporterDeclaredName, 'declared_at' => '2026-10-31T09:00:00.000000Z'],
            'subject_declaration' => [
                'name' => $subjectDeclaredName,
                'declared_at' => $subjectDeclaredName === null ? null : '2026-10-31T09:00:00.000000Z',
            ],
            'created_at' => '2026-10-31T09:00:00.000000Z',
            'updated_at' => '2026-10-31T09:00:00.000000Z',
            'meta' => ['can' => ['record_subject_declaration' => $subjectDeclaredName === null]],
        ];
    }

    /**
     * The platform's plain 422 refusal, which carries a message but no
     * per-field errors.
     */
    private static function unprocessable(string $message, Fake $fake): mixed
    {
        return Json::message($message, 422, ['team_id' => $fake->teamId()]);
    }
}
