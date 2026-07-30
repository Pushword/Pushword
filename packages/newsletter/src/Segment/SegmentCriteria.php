<?php

namespace Pushword\Newsletter\Segment;

use DateTimeImmutable;

/**
 * The segment language: a flat list of conditions, all of which must hold.
 *
 *     [
 *       {"field": "tag",                    "op": "has",       "value": "AmTrek"},
 *       {"field": "createdAt",              "op": "olderThan", "value": "7d"},
 *       {"field": "prop.lastBoughtProduct", "op": "=",         "value": "tmb"}
 *     ]
 *
 * A rule that needs OR says so, in the shape {@see CriteriaGroup} defines:
 *
 *     {"any": [
 *       {"field": "tag", "op": "has", "value": "AmTrek"},
 *       {"field": "tag", "op": "has", "value": "AmTrek-VIP"}
 *     ]}
 *
 * Two campaigns do not replace that `any`: a contact carrying both tags would be
 * in both, and be mailed twice.
 *
 * The same list drives a campaign segment, an automation's enrollment rule and
 * its stop condition, so there is one thing to learn and one thing to test.
 */
final class SegmentCriteria
{
    public const string PROP_PREFIX = 'prop.';

    /** Operators accepted for each plain field. `prop.*` is handled apart. */
    public const array FIELD_OPERATORS = [
        'tag' => ['has', 'hasNot'],
        'createdAt' => ['olderThan', 'newerThan'],
        'confirmedAt' => ['olderThan', 'newerThan'],
        'locale' => ['=', '!='],
    ];

    public const array PROP_OPERATORS = ['=', '!=', 'isSet', 'isNotSet'];

    /** Operators that carry no value. */
    public const array VALUELESS_OPERATORS = ['isSet', 'isNotSet'];

    private const string DURATION_PATTERN = '/^(\d+)([mhdw])$/';

    /**
     * Validate and normalise a raw rule: its operator, and its conditions.
     *
     * @param array<mixed> $criteria
     *
     * @return array{any: bool, conditions: list<array{field: string, op: string, value: string}>}
     *
     * @throws SegmentException
     */
    public static function normalize(array $criteria): array
    {
        ['any' => $any, 'conditions' => $conditions] = CriteriaGroup::unwrap($criteria);

        return ['any' => $any, 'conditions' => self::normalizeConditions($conditions)];
    }

    /**
     * @param array<mixed> $conditions
     *
     * @return list<array{field: string, op: string, value: string}>
     *
     * @throws SegmentException
     */
    private static function normalizeConditions(array $conditions): array
    {
        $normalized = [];

        foreach (array_values($conditions) as $index => $condition) {
            if (! \is_array($condition)) {
                throw new SegmentException(\sprintf('Condition #%d is not an object.', $index));
            }

            $field = self::readString($condition, 'field', $index);
            $op = self::readString($condition, 'op', $index);
            $value = isset($condition['value']) && is_scalar($condition['value']) ? (string) $condition['value'] : '';

            self::assertOperator($field, $op, $index);

            if (\in_array($op, self::VALUELESS_OPERATORS, true)) {
                $value = '';
            } elseif ('' === $value) {
                throw new SegmentException(\sprintf('Condition #%d (%s %s) needs a value.', $index, $field, $op));
            }

            if (\in_array($op, ['olderThan', 'newerThan'], true)) {
                self::parseDuration($value, $index);
            }

            $normalized[] = ['field' => $field, 'op' => $op, 'value' => $value];
        }

        return $normalized;
    }

    /** @throws SegmentException */
    public static function validate(mixed $criteria): void
    {
        if (! \is_array($criteria)) {
            throw new SegmentException('Criteria must be a list of conditions.');
        }

        self::normalize($criteria);
    }

    /**
     * The admin edits criteria as JSON in a textarea; this is the round trip.
     *
     * @param array<mixed> $criteria
     */
    public static function toJson(array $criteria): string
    {
        if ([] === $criteria) {
            return '';
        }

        return json_encode($criteria, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<mixed> the rule, in the shape it is stored
     *
     * @throws SegmentException
     */
    public static function fromJson(?string $json): array
    {
        $json = trim((string) $json);

        if ('' === $json) {
            return [];
        }

        $decoded = json_decode($json, true);

        if (! \is_array($decoded)) {
            throw new SegmentException('Criteria must be a JSON list of conditions.');
        }

        $rule = self::normalize($decoded);

        return CriteriaGroup::wrap($rule['any'], $rule['conditions']);
    }

    public static function isProperty(string $field): bool
    {
        return str_starts_with($field, self::PROP_PREFIX);
    }

    /** The JSON path a `prop.<key>` field reads. */
    public static function propertyPath(string $field): string
    {
        return '$.'.substr($field, \strlen(self::PROP_PREFIX));
    }

    /**
     * Resolve a duration ("7d", "90m") into the instant it points back to.
     *
     * @throws SegmentException
     */
    public static function threshold(string $duration, DateTimeImmutable $now): DateTimeImmutable
    {
        [$amount, $unit] = self::parseDuration($duration, 0);

        $minutes = $amount * match ($unit) {
            'm' => 1,
            'h' => 60,
            'd' => 60 * 24,
            'w' => 60 * 24 * 7,
            default => throw new SegmentException('Unknown duration unit "'.$unit.'".'),
        };

        return $now->modify('-'.$minutes.' minutes');
    }

    /**
     * @param array<mixed> $condition
     *
     * @throws SegmentException
     */
    private static function readString(array $condition, string $key, int $index): string
    {
        $value = $condition[$key] ?? null;

        if (! \is_string($value) || '' === trim($value)) {
            throw new SegmentException(\sprintf('Condition #%d is missing "%s".', $index, $key));
        }

        return trim($value);
    }

    /** @throws SegmentException */
    private static function assertOperator(string $field, string $op, int $index): void
    {
        $allowed = self::isProperty($field)
            ? self::PROP_OPERATORS
            : (self::FIELD_OPERATORS[$field] ?? null);

        if (null === $allowed) {
            throw new SegmentException(\sprintf('Condition #%d: unknown field "%s". Known fields: %s, prop.<key>.', $index, $field, implode(', ', array_keys(self::FIELD_OPERATORS))));
        }

        if (self::isProperty($field) && self::PROP_PREFIX === $field) {
            throw new SegmentException(\sprintf('Condition #%d: "prop." needs a property name.', $index));
        }

        if (! \in_array($op, $allowed, true)) {
            throw new SegmentException(\sprintf('Condition #%d: operator "%s" does not apply to "%s". Allowed: %s.', $index, $op, $field, implode(', ', $allowed)));
        }
    }

    /**
     * @return array{int, string}
     *
     * @throws SegmentException
     */
    private static function parseDuration(string $duration, int $index): array
    {
        if (1 !== preg_match(self::DURATION_PATTERN, $duration, $matches)) {
            throw new SegmentException(\sprintf('Condition #%d: "%s" is not a duration (expected e.g. 90m, 6h, 7d, 2w).', $index, $duration));
        }

        return [(int) $matches[1], $matches[2]];
    }
}
