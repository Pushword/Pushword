<?php

namespace Pushword\Core\PropertySchema;

enum PagePropertyType: string
{
    case String = 'string';
    case Int = 'int';
    case Float = 'float';
    case Bool = 'bool';
    case Date = 'date';
    case List = 'list';

    public function accepts(mixed $value): bool
    {
        return match ($this) {
            self::String => \is_string($value),
            self::Int => \is_int($value),
            self::Float => \is_float($value) || \is_int($value),
            self::Bool => \is_bool($value),
            // Yaml::parse turns an unquoted `2024-06-01` into a Unix timestamp,
            // a quoted one stays a string — both are valid authoring forms.
            self::Date => \is_int($value) || (\is_string($value) && false !== strtotime($value)),
            self::List => \is_array($value),
        };
    }

    /** Whether `< > <= >=` compare this type meaningfully (numeric cast in SQL). */
    public function isComparable(): bool
    {
        return match ($this) {
            self::Int, self::Float, self::Date => true,
            default => false,
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
