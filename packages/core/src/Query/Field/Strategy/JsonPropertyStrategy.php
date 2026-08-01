<?php

namespace Pushword\Core\Query\Field\Strategy;

use InvalidArgumentException;
use Pushword\Core\Query\Field\FieldCompilation;
use Pushword\Core\Query\Field\FieldStrategy;

/**
 * A member of a JSON object column — `prop.lastBoughtProduct` and the like.
 *
 * Read through `JSON_SCALAR` rather than a substring match on the serialised
 * JSON: a substring depends on how the encoder spaced its output and only ever
 * matched string values, so `prop.count = 3` could not work at all.
 *
 * `< > <= >=` compile only when the property is declared int, float or date in
 * `page_properties` — the declared type is what licenses the numeric cast
 * (`JSON_NUMBER`), without which MySQL/MariaDB would compare lexically.
 */
final readonly class JsonPropertyStrategy implements FieldStrategy
{
    public const string PREFIX = 'prop.';

    private const array COMPARISONS = ['<', '>', '<=', '>='];

    /**
     * The property key charset: word characters, dots for nested paths,
     * dashes. Anything else would be interpolated into the DQL JSON path
     * unescaped — reject it instead.
     */
    public const string KEY_PATTERN = '/^[A-Za-z0-9_.\-]+$/';

    public function __construct(
        private string $column,
        private bool $comparable = false,
    ) {
    }

    public function operators(): array
    {
        return ['=', '!=', 'isSet', 'isNotSet', ...self::COMPARISONS];
    }

    public function compile(FieldCompilation $compilation): string
    {
        $key = substr($compilation->field, \strlen(self::PREFIX));
        if (1 !== preg_match(self::KEY_PATTERN, $key)) {
            throw new InvalidArgumentException(\sprintf('Invalid property name in `%s`.', $compilation->field));
        }

        if (\in_array($compilation->operator, self::COMPARISONS, true)) {
            return $this->compileComparison($compilation);
        }

        $extract = \sprintf(
            "JSON_SCALAR(%s, '%s')",
            $compilation->column($this->column),
            self::path($compilation->field),
        );

        return match ($compilation->operator) {
            'isSet' => $extract.' IS NOT NULL',
            'isNotSet' => $extract.' IS NULL',
            // A missing property is not "different from x" — it is unknown. The
            // explicit IS NOT NULL keeps != from silently widening the match,
            // which is the opposite of what an absent template means.
            '!=' => $compilation->bind(
                \sprintf('(%s IS NOT NULL AND %s != :%s)', $extract, $extract, $compilation->parameter),
                $compilation->value,
            ),
            default => $compilation->bind(\sprintf('%s = :%s', $extract, $compilation->parameter), $compilation->value),
        };
    }

    private function compileComparison(FieldCompilation $compilation): string
    {
        if (! $this->comparable) {
            throw new InvalidArgumentException(\sprintf('`%s` does not support `%s`: declare the property as int, float or date in `page_properties` (see pw:schema:dump).', $compilation->field, $compilation->operator));
        }

        // The bound value must be numeric: SQLite orders every integer below
        // every text, so a string-bound '300' would never match a JSON number.
        $value = $compilation->value;
        if (\is_string($value) && is_numeric($value)) {
            $value += 0;
        }

        if (! \is_int($value) && ! \is_float($value)) {
            throw new InvalidArgumentException(\sprintf('`%s %s` needs a numeric value, got %s.', $compilation->field, $compilation->operator, get_debug_type($compilation->value)));
        }

        $number = \sprintf(
            "JSON_NUMBER(%s, '%s')",
            $compilation->column($this->column),
            self::path($compilation->field),
        );

        return $compilation->bind(\sprintf('%s %s :%s', $number, $compilation->operator, $compilation->parameter), $value);
    }

    /** The JSON path a `prop.<key>` field reads. */
    public static function path(string $field): string
    {
        return '$.'.substr($field, \strlen(self::PREFIX));
    }
}
