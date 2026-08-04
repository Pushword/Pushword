<?php

namespace Pushword\Newsletter\Segment;

/**
 * The one operator a rule carries, shared by both criteria languages so that a
 * rule over contacts and a rule over pages are read the same way: a bare list is
 * ANDed, `{"any": [...]}` ORs, `{"all": [...]}` says the default out loud.
 *
 * There is no nesting — one operator per expression is learnable, a tree is not,
 * which is the rule a `pages_list` search follows.
 */
final class CriteriaGroup
{
    /**
     * Split a raw rule into its operator and its still-unvalidated conditions.
     *
     * @param array<mixed> $criteria
     *
     * @return array{any: bool, conditions: array<mixed>}
     *
     * @throws SegmentException
     */
    public static function unwrap(array $criteria): array
    {
        $any = \array_key_exists('any', $criteria);

        if ($any && \array_key_exists('all', $criteria)) {
            throw new SegmentException('A rule matches "any" of its conditions or "all" of them, not both.');
        }

        $conditions = $any ? $criteria['any'] : ($criteria['all'] ?? $criteria);

        if (! \is_array($conditions)) {
            throw new SegmentException(\sprintf('"%s" must hold a list of conditions.', $any ? 'any' : 'all'));
        }

        return ['any' => $any, 'conditions' => $conditions];
    }

    /**
     * The shape a rule is stored and edited in. An `any` that came back bare
     * would silently become an `all`, so the operator travels with it.
     *
     * @param list<array<mixed>> $conditions a condition, or a group of its own
     *
     * @return array<mixed>
     */
    public static function wrap(bool $any, array $conditions): array
    {
        return $any ? ['any' => $conditions] : $conditions;
    }

    /**
     * The rule, narrowed by one more condition every row must also satisfy.
     *
     * An `any` is kept whole and nested rather than flattened: spreading its
     * conditions into the ANDed list would turn "either of those tags" into
     * "both of them", and appending to it would widen the rule by the very
     * condition meant to narrow it.
     *
     * @param array<mixed>         $criteria  the rule as stored, possibly empty
     * @param array<string,string> $condition
     *
     * @return array<mixed> the rule as stored
     *
     * @throws SegmentException
     */
    public static function and(array $criteria, array $condition): array
    {
        if ([] === $criteria) {
            return [$condition];
        }

        ['any' => $any, 'conditions' => $conditions] = self::unwrap($criteria);

        return $any
            ? [['any' => $conditions], $condition]
            : [...array_values($conditions), $condition];
    }
}
