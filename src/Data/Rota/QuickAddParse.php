<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Rota;

use SupportManager\Sdk\Enums\Rota\QuickAddStatus;

/**
 * The state of one quick-add parse — either the `202` handed back when text is
 * submitted, or a poll of its token.
 *
 * Mirrors the platform's `QuickAddController` payloads, which are flat
 * `{data: {...}}` bodies rather than JSON:API documents. `token` and
 * `expiresIn` are set only on submission; `result` only once `status` is
 * {@see QuickAddStatus::Completed}; `hint` only when it is
 * {@see QuickAddStatus::Failed}. `status` is null for a value this SDK does not
 * yet know — treat that as "stop polling", not as an error.
 */
final readonly class QuickAddParse
{
    public function __construct(
        public ?string $token,
        public ?QuickAddStatus $status,
        public ?int $expiresIn,
        public ?QuickAddParseResult $result,
        public ?string $hint,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The QuickAddController `data` payload.
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed>|null $result */
        $result = is_array($data['result'] ?? null) ? $data['result'] : null;

        return new self(
            token: isset($data['token']) ? (string) $data['token'] : null,
            status: isset($data['status']) ? QuickAddStatus::tryFrom((string) $data['status']) : null,
            expiresIn: isset($data['expires_in']) ? (int) $data['expires_in'] : null,
            result: $result === null ? null : QuickAddParseResult::fromArray($result),
            hint: isset($data['hint']) ? (string) $data['hint'] : null,
        );
    }

    /**
     * Whether the parse is still running and the token should be polled again.
     */
    public function isPending(): bool
    {
        return $this->status === QuickAddStatus::Pending;
    }

    /**
     * Whether the parse finished with a `result` to confirm.
     */
    public function isCompleted(): bool
    {
        return $this->status === QuickAddStatus::Completed && $this->result !== null;
    }

    /**
     * Whether the parser couldn't answer — fall back to the form, showing `hint`.
     */
    public function isFailed(): bool
    {
        return $this->status === QuickAddStatus::Failed;
    }
}
