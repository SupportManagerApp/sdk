<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Contract\Support;

use InvalidArgumentException;

/**
 * The two committed allowlists of contract violations:
 *  - tests/Contract/spec-exceptions.php: the spec is wrong and the platform
 *    code is right;
 *  - tests/Contract/known-sdk-bugs.php: the SDK is wrong, and a fixture keeps
 *    the SDK's wrong shape until the SDK is fixed.
 *
 * Most entries patch one operation's schema ({@see AllowlistEntry}). An entry
 * with `'missing' => true` instead names an operation the platform does not
 * have at all but the SDK calls, so tests of that call are not failed for it.
 */
final class Allowlist
{
    public const array FILES = [
        'spec-exceptions' => __DIR__.'/../spec-exceptions.php',
        'known-sdk-bugs' => __DIR__.'/../known-sdk-bugs.php',
    ];

    private static ?self $instance = null;

    /** @var array<string, list<AllowlistEntry>> */
    private array $byOperation = [];

    /** @var list<AllowlistEntry> */
    private array $entries = [];

    /** @var array<string, array{method: string, regex: string, description: string}> Missing operations by entry id. */
    private array $missing = [];

    /** @var array<string, true> Missing-operation entries some request used. */
    private array $usedMissing = [];

    private function __construct()
    {
        $spec = OpenApiSpec::instance();

        foreach (self::FILES as $source => $file) {
            /** @var list<array<string, mixed>> $rows */
            $rows = require $file;

            foreach ($rows as $index => $row) {
                if (($row['missing'] ?? false) === true) {
                    $this->addMissing($source, $index, $row, $spec);

                    continue;
                }

                $entry = AllowlistEntry::fromArray($source, $index, $row);

                if (! $spec->has($entry->operation)) {
                    throw new InvalidArgumentException("{$entry->id} names {$entry->operation}, which is not in the spec.");
                }

                $this->entries[] = $entry;
                $this->byOperation[$entry->operation][] = $entry;
            }
        }
    }

    public static function instance(): self
    {
        return self::$instance ??= new self;
    }

    /**
     * @return list<AllowlistEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @return list<AllowlistEntry>
     */
    public function forOperation(string $operation): array
    {
        return $this->byOperation[$operation] ?? [];
    }

    /**
     * Whether a request the spec has no operation for is an allowlisted
     * missing operation (and remember that it was needed).
     */
    public function allowsMissing(string $method, string $path): bool
    {
        foreach ($this->missing as $id => $missing) {
            if ($missing['method'] === $method && preg_match($missing['regex'], $path) === 1) {
                $this->usedMissing[$id] = true;

                return true;
            }
        }

        return false;
    }

    /**
     * Missing-operation entries no request needed, as "id: description".
     *
     * @return list<string>
     */
    public function unusedMissing(): array
    {
        $unused = [];

        foreach ($this->missing as $id => $missing) {
            if (! isset($this->usedMissing[$id])) {
                $unused[] = "{$id}: {$missing['description']}";
            }
        }

        return $unused;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function addMissing(string $source, int $index, array $row, OpenApiSpec $spec): void
    {
        $id = "{$source}#{$index}";
        $operation = (string) ($row['operation'] ?? '');
        $reason = (string) ($row['reason'] ?? '');

        if (preg_match('#^(GET|POST|PUT|PATCH|DELETE) (/\S*)$#', $operation, $m) !== 1 || $reason === '') {
            throw new InvalidArgumentException("{$id} needs an 'operation' ('METHOD /template') and a 'reason'.");
        }

        if ($spec->has($operation)) {
            throw new InvalidArgumentException("{$id}: the spec now has {$operation}; remove the 'missing' entry.");
        }

        $this->missing[$id] = [
            'method' => $m[1],
            'regex' => '#^'.preg_replace('/\\\\\{[^}]+\\\\\}/', '[^/]+', preg_quote($m[2], '#')).'$#',
            'description' => "{$operation} (missing from the platform): {$reason}",
        ];
    }
}
