<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Concerns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use SupportManager\Sdk\Data\DeviceVerificationChallenge;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\DeviceVerificationRequiredException;
use SupportManager\Sdk\Exceptions\InvalidCredentialsException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;

/**
 * One error model for the whole SDK.
 *
 * Both the authentication client and the tenant-scoped resource layer run their
 * responses through here, so a 422 on `journeys()->query()->get()` surfaces the
 * same {@see ApiValidationException} a 422 on `login()` does — rather than
 * silently decoding into an empty result. Connection failures are normalised
 * too, so callers only ever catch {@see SupportManagerApiException} (and its
 * subclasses), never a raw Guzzle/Illuminate transport exception.
 */
trait MapsApiErrors
{
    /**
     * Throw the appropriate typed exception for an error response, or return
     * quietly when the response is successful (or its status is explicitly
     * allowed, e.g. a 404 a caller treats as "absent").
     *
     * The `$context` only labels the warning logged for an unexpected status;
     * it never reaches the exception message. Omitted, it defaults to the
     * request's path (e.g. `/api/v1/tasks/count`), which is what a caller would
     * otherwise type by hand.
     *
     * @param  string|null  $context  A label for the log entry; defaults to the request path.
     * @param  list<int>  $allowStatuses  Statuses to treat as non-errors.
     */
    protected function throwForErrorResponse(Response $response, ?string $context = null, array $allowStatuses = []): void
    {
        if ($response->successful() || in_array($response->status(), $allowStatuses, true)) {
            return;
        }

        $context ??= $response->effectiveUri()?->getPath() ?? 'request';

        $status = $response->status();
        $body = $response->json() ?? [];
        $apiMessage = is_array($body) ? ($body['message'] ?? null) : null;

        if ($status === 403 && ($body['error_code'] ?? null) === 'DEVICE_VERIFICATION_REQUIRED') {
            throw new DeviceVerificationRequiredException(
                DeviceVerificationChallenge::fromArray($body['data'] ?? []),
                $apiMessage ?? 'Device verification required',
            );
        }

        if ($status === 401) {
            throw new InvalidCredentialsException($apiMessage ?? 'Your email or password is incorrect.', 401);
        }

        if ($status === 422) {
            throw new ApiValidationException($apiMessage ?? 'The information provided was invalid.', $body['errors'] ?? []);
        }

        // Anything else is unexpected (5xx, 404, an HTML error page, …). Log the
        // raw response and surface the status so the cause is visible.
        Log::warning('SupportManager API error', [
            'context' => $context,
            'status' => $status,
            'body' => mb_substr($response->body(), 0, 1000),
        ]);

        $message = $apiMessage ?? match (true) {
            $status >= 500 => "SupportManager is having problems right now (HTTP {$status}). Please try again shortly.",
            $status === 404 => 'The SupportManager service could not be found (HTTP 404). Check the API URL.',
            default => "The SupportManager request failed (HTTP {$status}).",
        };

        throw new SupportManagerApiException($message, $status, body: $response->body());
    }

    /**
     * Normalise a transport-level connection failure into a
     * {@see SupportManagerApiException}, logging the cause.
     */
    protected function throwForConnectionFailure(ConnectionException $e, string $context): SupportManagerApiException
    {
        Log::warning('SupportManager API unreachable', [
            'context' => $context,
            'error' => $e->getMessage(),
        ]);

        return new SupportManagerApiException('Unable to reach SupportManager. Please check your connection and try again.', 0, $e);
    }
}
