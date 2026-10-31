<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Tests\Arch\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;
use SupportManager\Sdk\Resources\Resource;

/**
 * Finds the SDK's classes by walking `src/`, so the architecture tests cover
 * every class on disk, whether or not anything has loaded it yet.
 */
final class Codebase
{
    public static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * Every PHP file under a directory of the package, e.g. `src/Data`.
     *
     * @return list<string> Paths relative to the package root.
     */
    public static function files(string $directory): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::root().'/'.$directory, RecursiveDirectoryIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = mb_substr($file->getPathname(), mb_strlen(self::root()) + 1);
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Every class, enum, interface and trait declared under a directory of
     * `src/`, by its PSR-4 name.
     *
     * @return list<class-string>
     */
    public static function classes(string $directory = ''): array
    {
        $classes = [];

        foreach (self::files($directory === '' ? 'src' : "src/{$directory}") as $file) {
            /** @var class-string $class */
            $class = 'SupportManager\\Sdk\\'.str_replace(['/', '.php'], ['\\', ''], mb_substr($file, mb_strlen('src/')));
            $classes[] = $class;
        }

        return $classes;
    }

    /**
     * The concrete resource classes, i.e. everything under `src/Resources`
     * except the base {@see Resource}.
     *
     * @return list<class-string<Resource<mixed>>>
     */
    public static function resources(): array
    {
        /** @var list<class-string<Resource<mixed>>> */
        return array_values(array_filter(self::classes('Resources'), static fn (string $class): bool => $class !== Resource::class));
    }

    /**
     * The source of a method, from its signature to its closing brace.
     */
    public static function source(ReflectionMethod $method): string
    {
        $lines = file((string) $method->getFileName());

        return implode('', array_slice($lines ?: [], (int) $method->getStartLine() - 1, (int) $method->getEndLine() - (int) $method->getStartLine() + 1));
    }

    /**
     * The public methods a class declares itself, rather than inherits.
     *
     * @param  class-string  $class
     * @return list<ReflectionMethod>
     */
    public static function ownPublicMethods(string $class): array
    {
        $reflection = new ReflectionClass($class);

        return array_values(array_filter(
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $class && ! $method->isConstructor(),
        ));
    }

    public static function isDeprecated(ReflectionMethod $method): bool
    {
        return str_contains((string) $method->getDocComment(), '@deprecated');
    }
}
