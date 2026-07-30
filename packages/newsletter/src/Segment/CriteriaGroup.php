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
     * @param list<array{field: string, op: string, value: string}> $conditions
     *
     * @return array<mixed>
     */
    public static function wrap(bool $any, array $conditions): array
    {
        return $any ? ['any' => $conditions] : $conditions;
    }
}
