<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Contract\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Which test classes (one per Pest file) have run in this process, so the
 * coverage and stale-allowlist checks only judge a complete run.
 */
final class TestRun
{
    /** @var array<string, true> */
    private static array $classes = [];

    public static function ran(string $class): void
    {
        self::$classes[$class] = true;
    }

    /**
     * Test files under tests/Feature and tests/Unit that have not run in this
     * process.
     *
     * @return list<string>
     */
    public static function missingFiles(): array
    {
        $root = dirname(__DIR__, 2);
        $missing = [];

        foreach (['Feature', 'Unit'] as $suite) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$suite}", FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! str_ends_with($file->getFilename(), 'Test.php')) {
                    continue;
                }

                $relative = mb_substr($file->getPathname(), mb_strlen($root) + 1, -4);
                $class = 'P\\Tests\\'.str_replace('/', '\\', $relative);

                if (! isset(self::$classes[$class])) {
                    $missing[] = $relative.'.php';
                }
            }
        }

        sort($missing);

        return $missing;
    }

    /**
     * Whether every Feature and Unit test file ran (at least partly).
     */
    public static function complete(): bool
    {
        return getenv('TEST_TOKEN') === false && self::missingFiles() === [];
    }
}
