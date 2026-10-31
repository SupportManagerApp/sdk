<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Data\Hydration;

use BackedEnum;
use ReflectionEnum;
use ReflectionNamedType;

/**
 * A type the {@see Hydrator} can cast to: a scalar, `array`, `mixed`, a backed
 * enum, or a nested DTO.
 *
 * @internal
 */
final readonly class Type
{
    public const string STRING = 'string';

    public const string INT = 'int';

    public const string FLOAT = 'float';

    public const string BOOL = 'bool';

    public const string ARRAY = 'array';

    public const string MIXED = 'mixed';

    public const string ENUM = 'enum';

    public const string OBJECT = 'object';

    private const array BUILTIN = [self::STRING, self::INT, self::FLOAT, self::BOOL, self::ARRAY, self::MIXED];

    /**
     * @param  'string'|'int'|'float'|'bool'|'array'|'mixed'|'enum'|'object'  $kind
     * @param  class-string|null  $class  The enum or DTO class, for those kinds.
     * @param  bool  $intBacked  Whether an enum is backed by `int` rather than `string`.
     */
    private function __construct(
        public string $kind,
        public ?string $class = null,
        public bool $intBacked = false,
    ) {}

    /**
     * The type for a declared parameter type or a `#[ListOf]` element name.
     *
     * @param  string  $context  Where the type came from, for error messages.
     */
    public static function named(string $name, string $context): self
    {
        if (in_array($name, self::BUILTIN, true)) {
            return new self($name);
        }

        if (enum_exists($name)) {
            if (! is_subclass_of($name, BackedEnum::class)) {
                throw new HydrationException("{$context}: enum {$name} is not backed, so it cannot be hydrated.");
            }

            $backing = (new ReflectionEnum($name))->getBackingType();

            return new self(self::ENUM, $name, $backing instanceof ReflectionNamedType && $backing->getName() === 'int');
        }

        if (class_exists($name)) {
            return new self(self::OBJECT, $name);
        }

        throw new HydrationException("{$context}: type {$name} is not supported.");
    }

    /**
     * Whether a present value must be an array to count as this type (anything
     * else is treated as missing).
     */
    public function needsArray(): bool
    {
        return $this->kind === self::ARRAY || $this->kind === self::OBJECT;
    }

    /**
     * Cast a present value. A non-nullable enum uses `from()` (so an unknown
     * value throws); a nullable one uses `tryFrom()`.
     */
    public function cast(mixed $value, bool $nullable): mixed
    {
        return match ($this->kind) {
            self::STRING => (string) $value,
            self::INT => (int) $value,
            self::FLOAT => (float) $value,
            self::BOOL => (bool) $value,
            self::ARRAY, self::MIXED => $value,
            self::ENUM => $this->toEnum($value, $nullable),
            self::OBJECT => $this->toObject($value),
        };
    }

    /**
     * Cast one `#[ListOf]` element, or report that it does not fit.
     *
     * @return array{bool, mixed} Whether to keep the element, and its value.
     */
    public function castElement(mixed $value): array
    {
        return match ($this->kind) {
            self::ARRAY => [is_array($value), $value],
            self::OBJECT => is_array($value) ? [true, $this->toObject($value)] : [false, null],
            self::ENUM => (is_string($value) || is_int($value)) && ($case = $this->toEnum($value, true)) !== null
                ? [true, $case]
                : [false, null],
            self::MIXED => [true, $value],
            default => is_scalar($value) ? [true, $this->cast($value, false)] : [false, null],
        };
    }

    /**
     * The value a non-nullable parameter gets when its key is missing and it
     * has no default: `''`, `0`, `0.0`, `false`, `[]`, `null` for `mixed`, a
     * DTO hydrated from `[]`, and — for an enum — whatever `from('')` gives
     * (normally a `ValueError`).
     */
    public function zero(): mixed
    {
        return match ($this->kind) {
            self::STRING => '',
            self::INT => 0,
            self::FLOAT => 0.0,
            self::BOOL => false,
            self::ARRAY => [],
            self::MIXED => null,
            self::ENUM => $this->toEnum('', false),
            self::OBJECT => $this->toObject([]),
        };
    }

    private function toEnum(mixed $value, bool $nullable): ?BackedEnum
    {
        /** @var class-string<BackedEnum> $enum */
        $enum = $this->class;

        if ($value instanceof $enum) {
            return $value;
        }

        $backing = $this->intBacked ? (int) $value : (string) $value;

        return $nullable ? $enum::tryFrom($backing) : $enum::from($backing);
    }

    /**
     * Build a nested DTO through its own `fromArray()` when it has one (so any
     * custom logic there still runs), or hydrate it directly otherwise.
     */
    private function toObject(mixed $value): object
    {
        /** @var class-string $class */
        $class = $this->class;
        $data = is_array($value) ? $value : [];

        if (method_exists($class, 'fromArray')) {
            /** @var object */
            return $class::fromArray($data);
        }

        return Hydrator::hydrate($class, $data);
    }
}
