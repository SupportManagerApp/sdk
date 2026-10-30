<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Rota;

use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Data\Rota\QuickAddParse;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * Quick add — turn a dictated or typed phrase ("dentist tuesday half three")
 * into a rota entry the user can confirm.
 *
 * Two calls, because the platform's AI provider takes seconds: `parse()` hands
 * the text to the queue and returns a token, and `poll()` reads its outcome
 * until it is no longer pending (a second between polls is plenty). The token
 * belongs to the user who created it and lives for `expiresIn` seconds.
 *
 * **Parsing creates nothing.** Render the result for confirmation, then call
 * `shifts()->create()` or `appointments()->create()` yourself. Like the
 * keysafe, the platform's `QuickAddController` returns flat `{data: {...}}`
 * bodies rather than JSON:API documents, so `data` is read directly.
 *
 * ```php
 * $parse = SupportManager::quickAdd()->parse('dentist tuesday half three', 'Europe/London');
 * $state = SupportManager::quickAdd()->poll($parse->token);
 *
 * if ($state?->isCompleted()) {
 *     SupportManager::appointments()->create($state->result->toAppointmentPayload());
 * }
 * ```
 *
 * @extends resource<QuickAddParse>
 */
final class QuickAddResource extends Resource
{
    /**
     * Submit text for parsing, returning the pending parse and its token. The
     * timezone (an IANA identifier) resolves phrases like "half three"; omitted,
     * the platform's own zone is used.
     */
    public function parse(string $text, ?string $timezone = null): ?QuickAddParse
    {
        return $this->state($this->post($this->path(), array_filter([
            'text' => $text,
            'timezone' => $timezone,
        ], static fn (mixed $value): bool => $value !== null)), $this->path());
    }

    /**
     * The current state of a parse, or null when the token is unknown, expired
     * or somebody else's (the platform 404s all three alike). Pending and
     * failed are both ordinary answers, not errors.
     */
    public function poll(string $token): ?QuickAddParse
    {
        $response = $this->get("{$this->path()}/{$token}");

        if ($response->status() === 404) {
            return null;
        }

        return $this->state($response, "{$this->path()}/{token}");
    }

    protected function path(): string
    {
        return 'rota/quick-add/parse';
    }

    protected function hydrate(ResourceObject $object): QuickAddParse
    {
        return QuickAddParse::fromArray($object->flat());
    }

    /**
     * Parse the controller's flat (non-JSON:API) `data` body into a DTO.
     */
    private function state(Response $response, string $context): ?QuickAddParse
    {
        $this->throwForErrorResponse($response, $context);

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        if (! isset($body['data']) || ! is_array($body['data'])) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $body['data'];

        return QuickAddParse::fromArray($data);
    }
}
