<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Client;

use Closure;
use Fiber;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use LogicException;
use Psr\Http\Message\RequestInterface;
use Throwable;
use WeakMap;

/**
 * Runs several SDK calls side by side, behind
 * {@see \SupportManager\Sdk\SupportManager::concurrently()} and
 * {@see \SupportManager\Sdk\SupportManager::settle()}.
 *
 * Each call runs in its own {@see Fiber}, its "slot", so resource methods stay
 * exactly as they are: synchronous, typed, and mapping errors the usual way.
 * When a slot's request is about to block, the {@see self::middleware()} that
 * {@see AuthenticatedApi} adds to slot requests pauses the slot instead,
 * having already added the transfer to the client's shared curl multi handle.
 * Once every slot is paused or finished, the coordinator completes the paused
 * transfers together, resumes each slot with its own result, and repeats
 * until every slot has finished. A slot that makes several requests in turn
 * (a page walk, a team lookup first) takes part in several rounds.
 *
 * The slots take turns rather than run in parallel, so session state needs no
 * locking. Token renewal relies on that: see {@see AuthenticatedApi::send()}.
 *
 * @internal Hosts reach this through the SupportManager entry point.
 */
final class Concurrency
{
    /** @var WeakMap<Fiber<mixed, mixed, mixed, mixed>, true>|null */
    private static ?WeakMap $slots = null;

    /**
     * Whether the running code is inside a slot.
     */
    public static function inSlot(): bool
    {
        $fiber = Fiber::getCurrent();

        return $fiber !== null && isset(self::slots()[$fiber]);
    }

    /**
     * Guzzle middleware for a request made inside a slot: start the transfer
     * on the shared multi handle without waiting for it, and pause the slot
     * when something waits on its result.
     *
     * Requests that never reach the transport (an `Http::fake()` stub) pause
     * the slot all the same, so faked runs interleave exactly as real ones do.
     */
    public static function middleware(): Closure
    {
        return static fn (callable $handler): Closure => static function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            // A synchronous request would go to curl's blocking handler and
            // run on its own; async joins the multi handle the coordinator
            // drives.
            $options['synchronous'] = false;

            /** @var PromiseInterface $transfer */
            $transfer = $handler($request, $options);

            $result = null;
            $result = new Promise(static function () use (&$result, $transfer): void {
                assert($result instanceof Promise);

                // Back here once the coordinator has completed the transfer.
                Fiber::suspend($transfer);

                try {
                    $result->resolve($transfer->wait());
                } catch (Throwable $e) {
                    $result->reject($e);
                }
            });

            return $result;
        };
    }

    /**
     * Run each task in its own slot, returning one {@see Settled} per task
     * under the task's key, in the order given. A task that throws settles
     * as a failure without disturbing the others.
     *
     * @template TKey of array-key
     *
     * @param  array<TKey, Closure(): mixed>  $tasks
     * @return array<TKey, Settled>
     */
    public static function run(array $tasks): array
    {
        /** @var array<TKey, Fiber<mixed, mixed, mixed, mixed>> $fibers */
        $fibers = [];
        /** @var array<TKey, PromiseInterface> $waiting */
        $waiting = [];
        /** @var array<TKey, Settled> $results */
        $results = [];

        foreach ($tasks as $key => $task) {
            $fiber = new Fiber($task);
            self::slots()[$fiber] = true;
            $fibers[$key] = $fiber;

            self::advance($key, $fiber, static fn (): mixed => $fiber->start(), $waiting, $results);
        }

        while ($waiting !== []) {
            $round = $waiting;
            $waiting = [];

            // Every transfer in the round sits on the same curl multi handle,
            // so waiting on the first drives them all at once; the rest are
            // usually done by the time they are waited on. Outcomes are read
            // again by each slot, so failures are not handled here.
            foreach ($round as $transfer) {
                try {
                    $transfer->wait();
                } catch (Throwable) {
                }
            }

            foreach (array_keys($round) as $key) {
                $fiber = $fibers[$key];

                self::advance($key, $fiber, static fn (): mixed => $fiber->resume(), $waiting, $results);
            }
        }

        $ordered = [];

        foreach (array_keys($tasks) as $key) {
            $ordered[$key] = $results[$key];
        }

        return $ordered;
    }

    /**
     * Run a slot until it pauses or finishes, and record which.
     *
     * @template TKey of array-key
     *
     * @param  TKey  $key
     * @param  Fiber<mixed, mixed, mixed, mixed>  $fiber
     * @param  Closure(): mixed  $step
     * @param  array<TKey, PromiseInterface>  $waiting
     * @param  array<TKey, Settled>  $results
     */
    private static function advance(int|string $key, Fiber $fiber, Closure $step, array &$waiting, array &$results): void
    {
        try {
            $paused = $step();
        } catch (Throwable $e) {
            $results[$key] = Settled::fromException($e);

            return;
        }

        if ($fiber->isTerminated()) {
            $results[$key] = Settled::fromValue($fiber->getReturn());

            return;
        }

        if (! $paused instanceof PromiseInterface) {
            $results[$key] = Settled::fromException(new LogicException(
                'A concurrent SupportManager call paused for something other than an SDK request. '
                .'Do not suspend fibers yourself inside SupportManager::concurrently().',
            ));

            return;
        }

        $waiting[$key] = $paused;
    }

    /**
     * @return WeakMap<Fiber<mixed, mixed, mixed, mixed>, true>
     */
    private static function slots(): WeakMap
    {
        return self::$slots ??= new WeakMap;
    }
}
