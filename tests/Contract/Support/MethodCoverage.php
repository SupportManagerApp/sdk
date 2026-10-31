<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Contract\Support;

use Closure;
use Psr\Http\Message\RequestInterface;
use ReflectionClass;
use ReflectionMethod;
use SupportManager\Sdk\Resources\Resource;

/**
 * Which public resource methods sent an HTTP request during this process, by
 * `Class::method`, for the method-coverage check in
 * {@see \SupportManager\Sdk\Tests\Contract\CoverageTest}.
 *
 * {@see \SupportManager\Sdk\Tests\TestCase} registers {@see self::middleware()}
 * as a global request middleware on the HTTP client factory. Guzzle runs it
 * synchronously inside the call that sends the request (before the faked
 * response is chosen), so the backtrace still holds the resource method that
 * made the call. The outermost public method on a {@see Resource} object is
 * credited, keyed by the object's concrete class: a `Listable` or `Findable`
 * method counts for the resource that uses the trait, a deprecated alias
 * counts for the alias rather than the method it forwards to, and a public
 * method another public method calls internally does not count.
 *
 * Requests no public resource method is on the stack for (the auth client,
 * a `ResourceQuery` terminal run after `query()` returned, a `Paginated::next()`
 * whose fetcher is private) credit nothing.
 *
 * The middleware only notes the caller and the request's method and path. A
 * caller is credited at the end of a passing test, by {@see self::credit()},
 * and only when the faked exchanges of that test include the same method and
 * path reaching a spec operation with no contract violation. A request that
 * was never recorded (a stub that throws a ConnectionException, say), went to
 * a non-API URL, or reached a route the spec lacks credits nothing, so a
 * credited method is one shown to hit a documented route.
 */
final class MethodCoverage
{
    /** @var array<string, true> */
    private static array $exercised = [];

    /** @var list<array{string, string}> This test's `Class::method` and `METHOD /path` pairs, not yet credited. */
    private static array $pending = [];

    /** @var array<string, bool> Whether `Class::method` is public, by key. */
    private static array $public = [];

    /**
     * @return Closure(RequestInterface): RequestInterface
     */
    public static function middleware(): Closure
    {
        return static function (RequestInterface $request): RequestInterface {
            $caller = self::caller();

            if ($caller !== null) {
                self::$pending[] = [$caller, self::requestKey($request)];
            }

            return $request;
        };
    }

    /**
     * Forget the callers noted so far, at the start of a test, so a failed
     * test's requests are never credited to the next one.
     */
    public static function reset(): void
    {
        self::$pending = [];
    }

    /**
     * Credit this test's noted callers whose request is among the given
     * contract-validated requests, then forget them.
     *
     * @param  iterable<RequestInterface>  $documented  Requests that reached a spec operation and matched the spec.
     */
    public static function credit(iterable $documented): void
    {
        $keys = [];

        foreach ($documented as $request) {
            $keys[self::requestKey($request)] = true;
        }

        foreach (self::$pending as [$caller, $request]) {
            if (isset($keys[$request])) {
                self::$exercised[$caller] = true;
            }
        }

        self::$pending = [];
    }

    /**
     * @return array<string, true>
     */
    public static function exercised(): array
    {
        return self::$exercised;
    }

    /**
     * Every public method each resource offers, its own or from a trait, as
     * `Class::method` mapped to the keys an entry in
     * `tests/Contract/request-free-methods.php` may list it under: that one,
     * and `Trait::method` when a trait provides it. The base {@see Resource}
     * has no public methods besides its constructor, which is left out.
     *
     * @param  list<class-string<Resource<mixed>>>  $resources
     * @return array<string, list<string>>
     */
    public static function publicMethods(array $resources): array
    {
        $methods = [];

        foreach ($resources as $resource) {
            $class = new ReflectionClass($resource);

            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isConstructor() || $method->getDeclaringClass()->getName() === Resource::class) {
                    continue;
                }

                $key = "{$resource}::{$method->getName()}";
                $methods[$key] = [$key];

                foreach ($class->getTraits() as $trait) {
                    if ($trait->hasMethod($method->getName()) && $trait->getFileName() === $method->getFileName()) {
                        $methods[$key][] = "{$trait->getName()}::{$method->getName()}";
                    }
                }
            }
        }

        ksort($methods);

        return $methods;
    }

    /**
     * The outermost public resource method on the current call stack.
     */
    private static function caller(): ?string
    {
        $outermost = null;

        foreach (debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT) as $frame) {
            $object = $frame['object'] ?? null;

            if (! $object instanceof Resource) {
                continue;
            }

            $key = $object::class.'::'.$frame['function'];

            if (self::isPublicMethod($object::class, $frame['function'], $key)) {
                $outermost = $key;
            }
        }

        return $outermost;
    }

    private static function requestKey(RequestInterface $request): string
    {
        return mb_strtoupper($request->getMethod()).' '.$request->getUri()->getPath();
    }

    private static function isPublicMethod(string $class, string $method, string $key): bool
    {
        return self::$public[$key] ??= method_exists($class, $method) && (new ReflectionMethod($class, $method))->isPublic();
    }
}
