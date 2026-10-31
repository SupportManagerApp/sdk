<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Accidents;

/**
 * One entry in the team's accident book: who had the accident, who recorded
 * it, what happened, and both people's typed declarations.
 *
 * Mirrors the platform's `AccidentReportResource`. The nested `subject`,
 * `reporter`, `reporter_declaration` and `subject_declaration` arrays are
 * flattened into prefixed fields. `riddor` is only sent to admins and managers,
 * so it is null for everyone else. `canRecordSubjectDeclaration` comes from the
 * resource's `meta.can` and says whether to offer
 * {@see \SupportManager\Sdk\Resources\Accidents\AccidentsResource::recordSubjectDeclaration()}.
 */
final readonly class AccidentReport
{
    public function __construct(
        public string $id,
        public int $reportNumber,
        public bool $riddorReportable,
        public ?int $subjectUserId,
        public string $subjectName,
        public ?string $subjectOccupation,
        public ?int $reporterUserId,
        public string $reporterName,
        public ?string $happenedAt,
        public string $location,
        public string $howItHappened,
        public ?string $cause,
        public ?string $injuryDetails,
        public ?string $reporterDeclaredName,
        public ?string $reporterDeclaredAt,
        public ?string $subjectDeclaredName,
        public ?string $subjectDeclaredAt,
        public ?AccidentRiddorOutcome $riddor,
        public bool $canRecordSubjectDeclaration,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A flattened JSON:API resource object.
     * @param  array<string, mixed>  $meta  The resource object's own `meta` member.
     */
    public static function fromArray(array $data, array $meta = []): self
    {
        /** @var array{user_id?: mixed, name?: mixed, occupation?: mixed} $subject */
        $subject = is_array($data['subject'] ?? null) ? $data['subject'] : [];

        /** @var array{user_id?: mixed, name?: mixed} $reporter */
        $reporter = is_array($data['reporter'] ?? null) ? $data['reporter'] : [];

        /** @var array{name?: mixed, declared_at?: mixed} $reporterDeclaration */
        $reporterDeclaration = is_array($data['reporter_declaration'] ?? null) ? $data['reporter_declaration'] : [];

        /** @var array{name?: mixed, declared_at?: mixed} $subjectDeclaration */
        $subjectDeclaration = is_array($data['subject_declaration'] ?? null) ? $data['subject_declaration'] : [];

        /** @var array{record_subject_declaration?: mixed} $can */
        $can = is_array($meta['can'] ?? null) ? $meta['can'] : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            reportNumber: (int) ($data['report_number'] ?? 0),
            riddorReportable: (bool) ($data['riddor_reportable'] ?? false),
            subjectUserId: isset($subject['user_id']) ? (int) $subject['user_id'] : null,
            subjectName: (string) ($subject['name'] ?? ''),
            subjectOccupation: isset($subject['occupation']) ? (string) $subject['occupation'] : null,
            reporterUserId: isset($reporter['user_id']) ? (int) $reporter['user_id'] : null,
            reporterName: (string) ($reporter['name'] ?? ''),
            happenedAt: isset($data['happened_at']) ? (string) $data['happened_at'] : null,
            location: (string) ($data['location'] ?? ''),
            howItHappened: (string) ($data['how_it_happened'] ?? ''),
            cause: isset($data['cause']) ? (string) $data['cause'] : null,
            injuryDetails: isset($data['injury_details']) ? (string) $data['injury_details'] : null,
            reporterDeclaredName: isset($reporterDeclaration['name']) ? (string) $reporterDeclaration['name'] : null,
            reporterDeclaredAt: isset($reporterDeclaration['declared_at']) ? (string) $reporterDeclaration['declared_at'] : null,
            subjectDeclaredName: isset($subjectDeclaration['name']) ? (string) $subjectDeclaration['name'] : null,
            subjectDeclaredAt: isset($subjectDeclaration['declared_at']) ? (string) $subjectDeclaration['declared_at'] : null,
            riddor: is_array($data['riddor'] ?? null) ? AccidentRiddorOutcome::fromArray($data['riddor']) : null,
            canRecordSubjectDeclaration: (bool) ($can['record_subject_declaration'] ?? false),
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }

    /**
     * Whether the person who had the accident has confirmed the report.
     */
    public function isConfirmedBySubject(): bool
    {
        return $this->subjectDeclaredAt !== null;
    }
}
