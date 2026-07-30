<?php

namespace Pushword\Newsletter\Content;

use Pushword\Newsletter\Segment\SegmentException;

/**
 * The page language: which published pages a {@see \Pushword\Newsletter\Entity\ContentTrigger}
 * reacts to. Same shape as {@see \Pushword\Newsletter\Segment\SegmentCriteria} —
 * a flat list of conditions, all of which must hold, no OR and no nesting — so
 * there is one thing to learn for both sides of a trigger:
 *
 *     [
 *       {"field": "ancestor",         "op": "=",          "value": "blog"},
 *       {"field": "template",         "op": "=",          "value": "article.html.twig"},
 *       {"field": "prop.noNewsletter","op": "isNotSet"}
 *     ]
 *
 * It says *which* pages, never *when*: the wait is the trigger's own delay.
 *
 * Having no OR, it leans on the page tree instead: `parentPage` names one
 * rubric, `ancestor` names the section a rubric belongs to — which is what
 * keeps a blog split in rubrics down to a single condition.
 *
 * A malformed list raises {@see SegmentException}, the same way a malformed
 * segment does — one grammar, one kind of mistake, one thing to catch.
 */
final class PageCriteria
{
    public const string PROP_PREFIX = 'prop.';

    /** Operators accepted for each plain field. `prop.*` is handled apart. */
    public const array FIELD_OPERATORS = [
        'slug' => ['startsWith', 'notStartsWith'],
        'template' => ['=', '!='],
        'parentPage' => ['=', '!='],
        'ancestor' => ['=', '!='],
    ];

    public const array PROP_OPERATORS = ['=', '!=', 'isSet', 'isNotSet'];

    /** Operators that carry no value. */
    public const array VALUELESS_OPERATORS = ['isSet', 'isNotSet'];

    /**
     * Validate and normalise a raw criteria list.
     *
     * @param array<mixed> $criteria
     *
     * @return array<int, array{field: string, op: string, value: string}>
     *
     * @throws SegmentException
     */
    public static function normalize(array $criteria): array
    {
        $normalized = [];

        foreach (array_values($criteria) as $index => $condition) {
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
     * @return array<int, array{field: string, op: string, value: string}>
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

        return self::normalize($decoded);
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

        if (self::PROP_PREFIX === $field) {
            throw new SegmentException(\sprintf('Condition #%d: "prop." needs a property name.', $index));
        }

        if (! \in_array($op, $allowed, true)) {
            throw new SegmentException(\sprintf('Condition #%d: operator "%s" does not apply to "%s". Allowed: %s.', $index, $op, $field, implode(', ', $allowed)));
        }
    }
}
