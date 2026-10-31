<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Accidents;

use SupportManager\Sdk\Data\Accidents\AccidentReport;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * The team's accident book.
 *
 * The collection (`query()`, `find()`, `all()`) — newest accident first,
 * sortable by `happened_at`/`report_number`, with `per_page` capped at 100 —
 * plus `create()` to record an accident and `recordSubjectDeclaration()` for
 * the person who had it to confirm the report later. Admins and managers see
 * every report; personal assistants see the ones they recorded or that are
 * about them. Recording the RIDDOR outcome is web-only. The platform 404s every
 * endpoint while the team has the accident book switched off.
 *
 * ```php
 * SupportManager::accidents()->query()->sort('-happened_at')->perPage(15)->get();
 * SupportManager::accidents()->create([
 *     'subject' => ['type' => AccidentSubjectType::Reporter],
 *     'happened_at' => '2026-10-30T14:15:00+00:00',
 *     'location' => 'Kitchen',
 *     'how_it_happened' => 'Slipped on a wet floor.',
 *     'reporter_declared_name' => 'Sam Carer',
 * ]);
 * SupportManager::accidents()->recordSubjectDeclaration(7, 'Alex Visitor');
 * ```
 *
 * @extends Resource<AccidentReport>
 */
final class AccidentsResource extends Resource
{
    /**
     * Record an accident, returning the stored report.
     *
     * `subject.type` decides who had it (see
     * {@see \SupportManager\Sdk\Enums\Accidents\AccidentSubjectType}): `me`
     * needs nothing else, `team_member` needs `subject.user_id`, and `other`
     * needs `subject.name` plus a `subject.capacity`
     * ({@see \SupportManager\Sdk\Enums\Accidents\AccidentSubjectCapacity}).
     * Backed enums can be passed as-is; they encode to their values.
     *
     * @param  array<string, mixed>  $data  `subject`, `happened_at` (ISO 8601, not in the future), `location`, `how_it_happened` and `reporter_declared_name` are required; `cause`, `injury_details` and `subject_declared_name` are optional.
     */
    public function create(array $data): ?AccidentReport
    {
        return $this->item($this->post($this->path(), $data));
    }

    /**
     * Confirm a report as the person who had the accident, by their typed name,
     * returning the updated report. The platform answers 422 (an
     * {@see \SupportManager\Sdk\Exceptions\ApiValidationException}) when the
     * report is already confirmed or the reporter had the accident; check
     * {@see AccidentReport::$canRecordSubjectDeclaration} first.
     */
    public function recordSubjectDeclaration(string|int $id, string $subjectDeclaredName): ?AccidentReport
    {
        return $this->item($this->post("{$this->path()}/{$id}/subject-declaration", [
            'subject_declared_name' => $subjectDeclaredName,
        ]));
    }

    protected function path(): string
    {
        return 'accidents';
    }

    protected function hydrate(ResourceObject $object): AccidentReport
    {
        return AccidentReport::fromArray($object->flat());
    }
}
