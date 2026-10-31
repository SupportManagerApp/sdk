<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Accidents;

use SupportManager\Sdk\Data\Hydration\Attributes\Can;
use SupportManager\Sdk\Data\Hydration\Attributes\From;
use SupportManager\Sdk\Data\Hydration\Hydrator;

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
        #[From('subject.user_id')]
        public ?int $subjectUserId,
        #[From('subject.name')]
        public string $subjectName,
        #[From('subject.occupation')]
        public ?string $subjectOccupation,
        #[From('reporter.user_id')]
        public ?int $reporterUserId,
        #[From('reporter.name')]
        public string $reporterName,
        public ?string $happenedAt,
        public string $location,
        public string $howItHappened,
        public ?string $cause,
        public ?string $injuryDetails,
        #[From('reporter_declaration.name')]
        public ?string $reporterDeclaredName,
        #[From('reporter_declaration.declared_at')]
        public ?string $reporterDeclaredAt,
        #[From('subject_declaration.name')]
        public ?string $subjectDeclaredName,
        #[From('subject_declaration.declared_at')]
        public ?string $subjectDeclaredAt,
        public ?AccidentRiddorOutcome $riddor,
        #[Can('record_subject_declaration')]
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
        return Hydrator::hydrate(self::class, [...$data, 'meta' => $meta]);
    }

    /**
     * Whether the person who had the accident has confirmed the report.
     */
    public function isConfirmedBySubject(): bool
    {
        return $this->subjectDeclaredAt !== null;
    }
}
