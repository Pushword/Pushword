<?php

namespace Pushword\Newsletter\Criteria;

use Pushword\Core\Query\Condition;
use Pushword\Core\Query\Conjunction;
use Pushword\Core\Query\Group;
use Pushword\Newsletter\Segment\CriteriaGroup;
use Pushword\Newsletter\Segment\SegmentException;

/**
 * What a criteria language is, independently of what it filters.
 *
 * A rule is a list of `{field, op, value}` objects carrying one operator, in the
 * shape {@see CriteriaGroup} defines, and a child of that list may be a group of
 * its own. Everything about that — how a condition is read, which operators a
 * `prop.*` field takes, how a valueless operator drops its value, how the whole
 * thing round-trips through the admin's textarea — is the same whether the rule
 * selects contacts or pages, and is written once here.
 *
 * It normalises into the same {@see Group}/{@see Condition} tree a `pages_list`
 * search parses into, so one compiler serves both.
 *
 * A subclass says what its language knows: its fields ({@see FIELD_OPERATORS}),
 * the fields it deliberately refuses and why ({@see refusals()}), the language
 * next door so a misplaced field can be named ({@see neighbour()}), and any
 * constraint on a value beyond "not empty" ({@see assertValue()}).
 */
abstract class AbstractCriteria
{
    public const string PROP_PREFIX = 'prop.';

    /**
     * Operators every language accepts on a `prop.*` field. A language whose
     * registry knows how to read those values further — a contact's dates, as
     * durations — widens it.
     *
     * @var list<string>
     */
    public const array PROP_OPERATORS = ['=', '!=', 'isSet', 'isNotSet'];

    /** Operators that carry no value. */
    public const array VALUELESS_OPERATORS = ['isSet', 'isNotSet'];

    /**
     * Operators whose value is a duration rather than free text. What
     * {@see assertValue()} checks, and what an editor reads to offer an amount
     * and a unit instead of a text box.
     *
     * @var list<string>
     */
    public const array DURATION_OPERATORS = [];

    /**
     * Whether a rule may also be written as a search string, the spelling
     * {@see fromSearch()} reads. Said out loud so the admin can offer it.
     */
    public const bool ACCEPTS_SEARCH = false;

    /** What this language filters, for an error message: "a page", "a contact". */
    public const string SUBJECT = '';

    /**
     * Operators accepted for each plain field. `prop.*` is handled apart.
     *
     * @var array<string, list<string>>
     */
    public const array FIELD_OPERATORS = [];

    /**
     * Validate a raw rule into the tree a compiler takes.
     *
     * @param array<mixed> $criteria
     *
     * @return Group|null null when the rule is empty — every row, not none
     *
     * @throws SegmentException
     */
    public static function normalize(array $criteria): ?Group
    {
        ['any' => $any, 'conditions' => $conditions] = CriteriaGroup::unwrap($criteria);

        return self::group($any, $conditions, '');
    }

    /** @throws SegmentException */
    public static function validate(mixed $criteria): void
    {
        if (! \is_array($criteria)) {
            throw new SegmentException('Criteria must be a list of conditions.');
        }

        static::normalize($criteria);
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
            // Which mistake it is has to be decided before it is reported. An
            // input opening on `[` or `{` was reaching for JSON, so a decode
            // failure there is a JSON failure; reading it as a search would
            // store a typo as a tag named `{ "field"…` and say nothing.
            if (str_starts_with($json, '[') || str_starts_with($json, '{')) {
                throw new SegmentException('Criteria must be a JSON list of conditions.');
            }

            // Otherwise a language may also be written as a search string. What
            // it parses into is validated exactly like a rule typed as JSON, and
            // stored as one — the string is an input, the list is the record.
            $decoded = static::toArray(static::fromSearch($json));
        }

        return static::toArray(static::normalize($decoded));
    }

    /**
     * The inverse of {@see normalize()}: the tree, back in the shape it is
     * stored and edited. Round-tripping through the tree is what normalises —
     * trimmed names, dropped values, and the operator travelling with its group.
     *
     * @return array<mixed>
     */
    public static function toArray(Group|Condition|null $node): array
    {
        if (null === $node) {
            return [];
        }

        if ($node instanceof Condition) {
            return CriteriaGroup::wrap(false, [self::conditionToArray($node)]);
        }

        return CriteriaGroup::wrap(Conjunction::Any === $node->conjunction, self::childrenToArray($node));
    }

    public static function isProperty(string $field): bool
    {
        return str_starts_with($field, self::PROP_PREFIX);
    }

    /**
     * Fields this language names but will not accept, and the reason, so a rule
     * written from the `pages_list` vocabulary fails with something to act on
     * rather than "unknown field".
     *
     * @return array<string, string>
     */
    protected static function refusals(): array
    {
        return [];
    }

    /**
     * The criteria language next door, so a field belonging to it can say so.
     *
     * @return class-string<self>|null
     */
    protected static function neighbour(): ?string
    {
        return null;
    }

    /**
     * Read the rule from a search string instead of JSON. A language that has no
     * such spelling says so rather than pretending the input was malformed JSON.
     *
     * @throws SegmentException
     */
    protected static function fromSearch(string $search): Group|Condition
    {
        throw new SegmentException('Criteria must be a JSON list of conditions.');
    }

    /**
     * A language may constrain a value further than "not empty" — the segment
     * durations do. Called once the field and the operator are known good.
     *
     * @throws SegmentException
     */
    protected static function assertValue(string $field, string $op, string $value, string $path): void
    {
    }

    /**
     * @param array<mixed> $conditions
     *
     * @throws SegmentException
     */
    private static function group(bool $any, array $conditions, string $path): ?Group
    {
        $children = [];

        foreach (array_values($conditions) as $index => $child) {
            $childPath = '' === $path ? (string) $index : $path.'.'.$index;

            if (! \is_array($child)) {
                throw new SegmentException(\sprintf('Condition #%s is not an object.', $childPath));
            }

            // A nested group says which operator it carries; only the outermost
            // one may be a bare list.
            if (\array_key_exists('any', $child) || \array_key_exists('all', $child)) {
                ['any' => $nestedAny, 'conditions' => $nested] = CriteriaGroup::unwrap($child);
                $nestedGroup = self::group($nestedAny, $nested, $childPath);

                if (null === $nestedGroup) {
                    throw new SegmentException(\sprintf('Condition #%s is an empty group.', $childPath));
                }

                $children[] = $nestedGroup;

                continue;
            }

            $children[] = self::condition($child, $childPath);
        }

        if ([] === $children) {
            return null;
        }

        // A group of one is kept as a group. It compiles to the same thing, and
        // it is what the editor wrote: collapsing `{"any": [x]}` would make the
        // textarea hand back `[x]`, so adding a second condition later would
        // silently turn the rule into an `all`.
        return new Group($any ? Conjunction::Any : Conjunction::All, $children);
    }

    /**
     * @param array<mixed> $condition
     *
     * @throws SegmentException
     */
    private static function condition(array $condition, string $path): Condition
    {
        $field = self::readString($condition, 'field', $path);
        $op = self::readString($condition, 'op', $path);
        $value = isset($condition['value']) && is_scalar($condition['value']) ? (string) $condition['value'] : '';

        self::assertOperator($field, $op, $path);

        if (\in_array($op, self::VALUELESS_OPERATORS, true)) {
            $value = '';
        } elseif ('' === $value) {
            throw new SegmentException(\sprintf('Condition #%s (%s %s) needs a value.', $path, $field, $op));
        }

        static::assertValue($field, $op, $value, $path);

        return new Condition($field, $op, $value);
    }

    /** @return array<string, string> */
    private static function conditionToArray(Condition $condition): array
    {
        return [
            'field' => $condition->field,
            'op' => $condition->operator,
            'value' => \is_scalar($condition->value) ? (string) $condition->value : '',
        ];
    }

    /** @return array<mixed> */
    private static function groupToArray(Group $group): array
    {
        // A nested group always names its operator, `all` included: coming back
        // as a bare list, it would be read as a condition.
        return [Conjunction::Any === $group->conjunction ? 'any' : 'all' => self::childrenToArray($group)];
    }

    /** @return list<array<mixed>> */
    private static function childrenToArray(Group $group): array
    {
        return array_map(
            static fn (Group|Condition $child): array => $child instanceof Group
                ? self::groupToArray($child)
                : self::conditionToArray($child),
            $group->children,
        );
    }

    /**
     * @param array<mixed> $condition
     *
     * @throws SegmentException
     */
    private static function readString(array $condition, string $key, string $path): string
    {
        $value = $condition[$key] ?? null;

        if (! \is_string($value) || '' === trim($value)) {
            throw new SegmentException(\sprintf('Condition #%s is missing "%s".', $path, $key));
        }

        return trim($value);
    }

    /** @throws SegmentException */
    private static function assertOperator(string $field, string $op, string $path): void
    {
        $allowed = self::isProperty($field)
            ? static::PROP_OPERATORS
            : (static::FIELD_OPERATORS[$field] ?? null);

        if (null === $allowed) {
            throw new SegmentException(self::unknownField($field, $path));
        }

        if (self::PROP_PREFIX === $field) {
            throw new SegmentException(\sprintf('Condition #%s: "prop." needs a property name.', $path));
        }

        if (! \in_array($op, $allowed, true)) {
            throw new SegmentException(\sprintf('Condition #%s: operator "%s" does not apply to "%s". Allowed: %s.', $path, $op, $field, implode(', ', $allowed)));
        }
    }

    private static function unknownField(string $field, string $path): string
    {
        $refusals = static::refusals();

        if (isset($refusals[$field])) {
            return \sprintf('Condition #%s: "%s" cannot be used here — it %s.', $path, $field, $refusals[$field]);
        }

        $neighbour = static::neighbour();

        if (null !== $neighbour && isset($neighbour::FIELD_OPERATORS[$field])) {
            return \sprintf('Condition #%s: "%s" filters %s, not %s.', $path, $field, $neighbour::SUBJECT, static::SUBJECT);
        }

        return \sprintf('Condition #%s: unknown field "%s". Known fields: %s, prop.<key>.', $path, $field, implode(', ', array_keys(static::FIELD_OPERATORS)));
    }
}
