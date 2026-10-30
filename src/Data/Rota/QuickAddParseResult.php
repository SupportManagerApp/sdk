<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

use SupportManager\Sdk\Enums\Rota\QuickAddIntent;

/**
 * What the quick-add parser understood. **Nothing has been created** — render
 * it for the user to confirm, then send it to the create endpoint its `intent`
 * names, via {@see self::toShiftPayload()} or {@see self::toAppointmentPayload()}.
 *
 * Mirrors the platform's `QuickAddParseResult`. Every field is always present
 * on the wire; which are meaningful depends on `intent` — appointment fields
 * are null on a shift, and shift fields null or false on an appointment.
 * `intent` is null for a value this SDK does not yet know.
 */
final readonly class QuickAddParseResult
{
    /**
     * @param  list<QuickAddAssigneeCandidate>  $assigneeCandidates
     * @param  list<string>  $tags
     */
    public function __construct(
        public ?QuickAddIntent $intent,
        public ?string $title,
        public ?string $assigneeName,
        public ?int $assigneeId,
        public array $assigneeCandidates,
        public ?string $date,
        public ?string $endDate,
        public ?string $startTime,
        public ?string $endTime,
        public ?int $durationMinutes,
        public ?string $location,
        public bool $isVirtual,
        public bool $isAllDay,
        public bool $isSleepingNight,
        public bool $isPrivate,
        public array $tags,
        public ?string $notes,
        public ?float $confidence,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The poll response's `result` payload.
     */
    public static function fromArray(array $data): self
    {
        $candidates = [];

        foreach (is_array($data['assignee_candidates'] ?? null) ? $data['assignee_candidates'] : [] as $candidate) {
            if (is_array($candidate)) {
                /** @var array<string, mixed> $candidate */
                $candidates[] = QuickAddAssigneeCandidate::fromArray($candidate);
            }
        }

        $tags = [];

        foreach (is_array($data['tags'] ?? null) ? $data['tags'] : [] as $tag) {
            if (is_string($tag)) {
                $tags[] = $tag;
            }
        }

        return new self(
            intent: isset($data['intent']) ? QuickAddIntent::tryFrom((string) $data['intent']) : null,
            title: isset($data['title']) ? (string) $data['title'] : null,
            assigneeName: isset($data['assignee_name']) ? (string) $data['assignee_name'] : null,
            assigneeId: isset($data['assignee_id']) ? (int) $data['assignee_id'] : null,
            assigneeCandidates: $candidates,
            date: isset($data['date']) ? (string) $data['date'] : null,
            endDate: isset($data['end_date']) ? (string) $data['end_date'] : null,
            startTime: isset($data['start_time']) ? (string) $data['start_time'] : null,
            endTime: isset($data['end_time']) ? (string) $data['end_time'] : null,
            durationMinutes: isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null,
            location: isset($data['location']) ? (string) $data['location'] : null,
            isVirtual: (bool) ($data['is_virtual'] ?? false),
            isAllDay: (bool) ($data['is_all_day'] ?? false),
            isSleepingNight: (bool) ($data['is_sleeping_night'] ?? false),
            isPrivate: (bool) ($data['is_private'] ?? false),
            tags: $tags,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
            confidence: isset($data['confidence']) ? (float) $data['confidence'] : null,
        );
    }

    /**
     * The `POST rota/shifts` body for this parse once the user has confirmed it.
     * Null fields are dropped so the platform applies its own defaults (an end
     * an hour after the start). `assignee_id` is null when the name was
     * ambiguous — settle it from `assigneeCandidates` and pass it in `$overrides`.
     *
     * @param  array<string, mixed>  $overrides  Fields the user corrected on confirmation.
     * @return array<string, mixed>
     */
    public function toShiftPayload(array $overrides = []): array
    {
        return [...self::withoutNulls([
            'assignee_id' => $this->assigneeId,
            'date' => $this->date,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'notes' => $this->notes,
            'is_sleeping_night' => $this->isSleepingNight,
            'is_private' => $this->isPrivate,
            'tags' => $this->tags,
        ]), ...$overrides];
    }

    /**
     * The `POST rota/appointments` body for this parse once the user has
     * confirmed it. Null fields are dropped so the platform applies its own
     * defaults; `duration_minutes` is passed through so a 15-minute blood test
     * doesn't block out an hour.
     *
     * @param  array<string, mixed>  $overrides  Fields the user corrected on confirmation.
     * @return array<string, mixed>
     */
    public function toAppointmentPayload(array $overrides = []): array
    {
        return [...self::withoutNulls([
            'title' => $this->title,
            'date' => $this->date,
            'end_date' => $this->endDate,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'duration_minutes' => $this->durationMinutes,
            'location' => $this->location,
            'notes' => $this->notes,
            'is_virtual' => $this->isVirtual,
            'is_all_day' => $this->isAllDay,
        ]), ...$overrides];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function withoutNulls(array $payload): array
    {
        return array_filter($payload, static fn (mixed $value): bool => $value !== null);
    }
}
