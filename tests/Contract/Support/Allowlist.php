<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Contract\Support;

use InvalidArgumentException;

/**
 * The two committed allowlists of contract violations:
 *  - tests/Contract/spec-exceptions.php: the spec is wrong and the platform
 *    code is right;
 *  - tests/Contract/known-sdk-bugs.php: the SDK is wrong, and a fixture keeps
 *    the SDK's wrong shape until the SDK is fixed (src/ owners: see each entry).
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

    private function __construct()
    {
        $spec = OpenApiSpec::instance();

        $files = self::FILES;

        // Work-in-progress entries while a refactor wave triages a module;
        // fold them into the two lists above before committing.
        foreach (glob(__DIR__.'/../_pending/*.php') ?: [] as $file) {
            $files['_pending/'.basename($file, '.php')] = $file;
        }

        foreach ($files as $source => $file) {
            /** @var list<array<string, mixed>> $rows */
            $rows = require $file;

            foreach ($rows as $index => $row) {
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
}
