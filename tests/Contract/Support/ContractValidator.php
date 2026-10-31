<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Contract\Support;

use GuzzleHttp\Psr7\ServerRequest;
use League\OpenAPIValidation\PSR7\Exception\NoResponseCode;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\RoutedServerRequestValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use League\OpenAPIValidation\Schema\Exception\SchemaMismatch;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Checks one request/response pair the SDK exchanged in a test against the
 * vendored spec, and remembers which operations were hit (for
 * {@see \SupportManager\Sdk\Tests\Contract\CoverageTest}) and which allowlist
 * entries were needed (so stale ones can be reported).
 *
 * One instance per process: the spec is parsed once, and each operation's
 * validators are built on first use.
 */
final class ContractValidator
{
    public const string API_PREFIX = '/api/v1';

    private const int MAX_SAMPLES_PER_TARGET = 25;

    private static ?self $instance = null;

    /** @var array<string, int> Calls per operation key. */
    private array $hits = [];

    /**
     * Messages that failed the bare spec but passed with the allowlists, kept
     * to work out afterwards which entries were really needed.
     *
     * @var array<string, array<string, array{operation: string, in: 'request'|int, message: MessageInterface}>>
     */
    private array $samples = [];

    /** @var array<string, RoutedServerRequestValidator|ResponseValidator> */
    private array $validators = [];

    public static function instance(): self
    {
        return self::$instance ??= new self;
    }

    /**
     * Validate one exchange. Returns human-readable violations (empty when it
     * matches the spec). Non-API URLs (an avatar CDN, signed S3 links) are not
     * the platform's API and are ignored.
     *
     * @return list<string>
     */
    public function check(RequestInterface $request, ?ResponseInterface $response, bool $record = true): array
    {
        $path = $request->getUri()->getPath();

        if (! str_starts_with($path, self::API_PREFIX.'/')) {
            return [];
        }

        $method = self::effectiveMethod($request);
        $relative = mb_substr($path, mb_strlen(self::API_PREFIX));
        $spec = OpenApiSpec::instance();
        $operation = $spec->match($method, $relative);

        if ($operation === null) {
            if (Allowlist::instance()->allowsMissing($method, $relative)) {
                return [];
            }

            $methods = $spec->methodsFor($relative);

            return [$methods === []
                ? "{$method} {$relative}: no operation in the spec has this path."
                : "{$method} {$relative}: the spec has this path only for ".implode(', ', $methods).'.'];
        }

        if ($record) {
            $this->hits[$operation] = ($this->hits[$operation] ?? 0) + 1;
        }

        $violations = [];

        // A request the platform answered 400/422 is one a test sent invalid on
        // purpose, to drive the SDK's error mapping, so only its response is
        // checked.
        $rejected = in_array($response?->getStatusCode(), [400, 422], true);

        if (! $rejected && ($error = $this->validate($operation, 'request', self::serverRequest($request), $record)) !== null) {
            $violations[] = "{$operation} request: {$error}";
        }

        if ($response !== null && ! self::isInfrastructureError($response)) {
            $status = $response->getStatusCode();

            if (($error = $this->validate($operation, $status, $response, $record)) !== null) {
                $violations[] = "{$operation} → {$status} response: {$error}";
            }
        }

        return $violations;
    }

    /**
     * The spec operation a request reaches, e.g. `GET /household/chores`, or
     * null for a non-API URL or a method and path the spec does not have
     * (even one a `missing` allowlist entry lets through).
     */
    public function operationFor(RequestInterface $request): ?string
    {
        $path = $request->getUri()->getPath();

        if (! str_starts_with($path, self::API_PREFIX.'/')) {
            return null;
        }

        return OpenApiSpec::instance()->match(self::effectiveMethod($request), mb_substr($path, mb_strlen(self::API_PREFIX)));
    }

    /**
     * Calls per operation key during this process.
     *
     * @return array<string, int>
     */
    public function hits(): array
    {
        return $this->hits;
    }

    /**
     * Allowlist entries no recorded exchange needed: either nothing exercised
     * the field, or the spec/fixture/SDK has since been fixed.
     *
     * @return list<AllowlistEntry>
     */
    public function staleEntries(): array
    {
        $stale = [];

        foreach (Allowlist::instance()->entries() as $entry) {
            $needed = false;
            $others = array_values(array_filter(
                Allowlist::instance()->forOperation($entry->operation),
                static fn (AllowlistEntry $other): bool => $other !== $entry,
            ));

            foreach ($this->samples[self::target($entry->operation, $entry->in)] ?? [] as $sample) {
                if ($this->failure($sample['operation'], $sample['in'], $sample['message'], $others) !== null) {
                    $needed = true;
                    break;
                }
            }

            if (! $needed) {
                $stale[] = $entry;
            }
        }

        return $stale;
    }

    /**
     * "at /data/0/attributes/foo: <schema error>" from the validator's
     * exception chain.
     */
    private static function describe(ValidationFailed $failure): string
    {
        $messages = [];
        $pointer = null;

        for ($e = $failure; $e instanceof Throwable; $e = $e->getPrevious()) {
            if ($e instanceof SchemaMismatch && $pointer === null) {
                $chain = $e->dataBreadCrumb()?->buildChain() ?? [];
                $pointer = '/'.implode('/', array_map(
                    static fn (mixed $segment): string => str_replace(['~', '/'], ['~0', '~1'], (string) $segment),
                    $chain,
                ));
            }

            $messages[] = $e->getMessage();
        }

        // The innermost message is the schema error; the outer ones say where.
        $detail = (string) end($messages);
        $context = $messages[0];

        return $pointer === null
            ? ($context === $detail ? $detail : "{$context} ({$detail})")
            : "at {$pointer}: {$detail}";
    }

    /**
     * A 5xx that is not JSON is a proxy or gateway page, not something the
     * platform's API documents.
     */
    private static function isInfrastructureError(ResponseInterface $response): bool
    {
        return $response->getStatusCode() >= 500
            && ! str_contains($response->getHeaderLine('Content-Type'), 'json');
    }

    /**
     * The method Laravel routes on: a POST whose form or multipart body carries
     * `_method` (method spoofing, for file uploads) counts as that method.
     */
    private static function effectiveMethod(RequestInterface $request): string
    {
        $method = mb_strtoupper($request->getMethod());

        if ($method !== 'POST' || ! str_contains($request->getHeaderLine('Content-Type'), 'multipart/form-data')) {
            return $method;
        }

        $body = $request->getBody();
        $contents = (string) $body;

        if ($body->isSeekable()) {
            $body->rewind();
        }

        return preg_match('/name="_method"\r?\n(?:[^\r\n]+\r?\n)*\r?\n(PUT|PATCH|DELETE)\r?\n/i', $contents, $m) === 1
            ? mb_strtoupper($m[1])
            : $method;
    }

    private static function serverRequest(RequestInterface $request): ServerRequestInterface
    {
        $body = $request->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        $contents = (string) $body;

        if ($body->isSeekable()) {
            $body->rewind();
        }

        $query = [];

        foreach (explode('&', $request->getUri()->getQuery()) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $query[urldecode($name)] = urldecode($value);
        }

        $server = new ServerRequest(
            self::effectiveMethod($request),
            $request->getUri(),
            $request->getHeaders(),
            $contents,
            $request->getProtocolVersion(),
        );

        return $server->withQueryParams($query);
    }

    /**
     * @param  'request'|'query'|'path'|int  $in
     */
    private static function target(string $operation, string|int $in): string
    {
        $in = in_array($in, ['query', 'path'], true) ? 'request' : $in;

        return "{$operation}|{$in}";
    }

    /**
     * Validate a request or response, first against the bare spec and then,
     * only if that fails, with the operation's allowlist entries applied.
     * Returns the formatted failure, or null when it passes.
     *
     * @param  'request'|int  $in
     */
    private function validate(string $operation, string|int $in, MessageInterface $message, bool $record): ?string
    {
        $failure = $this->failure($operation, $in, $message, []);
        $entries = Allowlist::instance()->forOperation($operation);

        if ($failure === null || $entries === []) {
            return $failure;
        }

        $failure = $this->failure($operation, $in, $message, $entries);

        if ($failure === null && $record) {
            $target = self::target($operation, $in);
            $hash = md5((string) $message->getBody()).($message instanceof ServerRequestInterface ? md5((string) $message->getUri()) : '');

            if (count($this->samples[$target] ?? []) < self::MAX_SAMPLES_PER_TARGET) {
                $this->samples[$target][$hash] = ['operation' => $operation, 'in' => $in, 'message' => $message];
            }
        }

        return $failure;
    }

    /**
     * @param  'request'|int  $in
     * @param  list<AllowlistEntry>  $patches
     */
    private function failure(string $operation, string|int $in, MessageInterface $message, array $patches): ?string
    {
        $spec = OpenApiSpec::instance();
        $address = new OperationAddress($spec->template($operation), mb_strtolower($spec->method($operation)));

        try {
            if ($message instanceof ServerRequestInterface) {
                $undocumented = $this->undocumentedQueryParameters($operation, $patches, $message);

                if ($undocumented !== []) {
                    return "query parameter '".implode("', '", $undocumented)."' is not documented (the platform ignores or rejects it).";
                }

                $validator = $this->validator($operation, $patches, request: true);
                assert($validator instanceof RoutedServerRequestValidator);
                $validator->validate($address, $message);
            } else {
                assert($message instanceof ResponseInterface);
                $validator = $this->validator($operation, $patches, request: false);
                assert($validator instanceof ResponseValidator);
                $validator->validate($address, $message);
            }
        } catch (NoResponseCode) {
            return 'the spec does not document this status (it documents '.implode(', ', $spec->statuses($operation)).').';
        } catch (ValidationFailed $e) {
            return self::describe($e);
        }

        return null;
    }

    /**
     * @param  list<AllowlistEntry>  $patches
     */
    private function validator(string $operation, array $patches, bool $request): RoutedServerRequestValidator|ResponseValidator
    {
        $key = ($request ? 'req|' : 'res|').$operation.'|'.implode(',', array_map(static fn (AllowlistEntry $e): string => $e->id, $patches));

        if (! isset($this->validators[$key])) {
            $builder = (new ValidatorBuilder)->fromSchema(OpenApiSpec::instance()->document($operation, $patches));
            $this->validators[$key] = $request ? $builder->getRoutedRequestValidator() : $builder->getResponseValidator();
        }

        return $this->validators[$key];
    }

    /**
     * Query parameter names the operation does not document. Names are
     * compared as sent (`filter[status]`), as the spec names them.
     *
     * @param  list<AllowlistEntry>  $patches
     * @return list<string>
     */
    private function undocumentedQueryParameters(string $operation, array $patches, ServerRequestInterface $request): array
    {
        $documented = [];
        $spec = OpenApiSpec::instance();
        $document = $spec->document($operation, $patches);
        $op = $document->paths->getPath($spec->template($operation))?->getOperations()[mb_strtolower($spec->method($operation))] ?? null;

        foreach ($op->parameters ?? [] as $parameter) {
            if (isset($parameter->in, $parameter->name) && $parameter->in === 'query') {
                $documented[] = $parameter->name;
            }
        }

        return array_values(array_diff(array_keys($request->getQueryParams()), $documented));
    }
}
