<?php

namespace Pushword\Core\Query;

use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use Pushword\Core\Query\Field\FieldCompilation;
use Pushword\Core\Query\Field\FieldRegistry;

/**
 * Walks a {@see Group}/{@see Condition} tree onto a query builder.
 *
 * It knows about trees and nothing about entities: which fields exist and what
 * they mean is a {@see FieldRegistry}'s business, so the same walk serves pages
 * and contacts.
 *
 * A field the registry does not know is compiled literally — field name,
 * operator and value straight into DQL. That is the raw array form's escape
 * hatch, deliberately kept: `pages()` and the static generator pass conditions
 * this engine has no vocabulary for, and validating them here would break
 * callers to no purpose. Surfaces that *do* validate their vocabulary do it
 * before they get here.
 */
final readonly class QueryCompiler
{
    public function __construct(private FieldRegistry $registry)
    {
    }

    /** Adds the tree to the builder, ANDed with whatever is already there. */
    public function apply(QueryBuilder $queryBuilder, Group|Condition $node, string $alias = 'p'): QueryBuilder
    {
        return $queryBuilder->andWhere($this->node($queryBuilder, $node, $alias));
    }

    private function node(QueryBuilder $queryBuilder, Group|Condition $node, string $alias): Andx|Orx|string
    {
        if ($node instanceof Condition) {
            return $this->condition($queryBuilder, $node, $alias);
        }

        $composite = Conjunction::Any === $node->conjunction
            ? $queryBuilder->expr()->orX()
            : $queryBuilder->expr()->andX();

        foreach ($node->children as $child) {
            $composite->add($this->node($queryBuilder, $child, $alias));
        }

        return $composite;
    }

    private function condition(QueryBuilder $queryBuilder, Condition $condition, string $alias): string
    {
        // Deterministic name: the parameter name is part of the DQL string, and
        // a random one would make every generated query unique — defeating
        // Doctrine's query cache and growing the cache pool without bound.
        $parameter = 'w'.\count($queryBuilder->getParameters());

        // Decided before any strategy sees it, and for every field: comparing to
        // NULL with anything but IS is never true in SQL, so a strategy binding
        // it would produce a query that silently matches nothing.
        if (null === $condition->value) {
            return $this->nullComparison($condition, $alias);
        }

        $strategy = null === $condition->keyPrefix ? $this->registry->strategy($condition->field) : null;

        // The operator has to belong to the field too. A registered name paired
        // with an operator the strategy never meant to handle is the array form
        // reaching past the vocabulary, so it takes the raw path rather than
        // being mangled.
        if (null !== $strategy && \in_array($condition->operator, $strategy->operators(), true)) {
            return $strategy->compile(new FieldCompilation(
                $queryBuilder,
                $alias,
                $condition->field,
                $condition->operator,
                $condition->value,
                $parameter,
            ));
        }

        return $this->raw($queryBuilder, $condition, $alias, $parameter);
    }

    private function nullComparison(Condition $condition, string $alias): string
    {
        if (! \in_array($condition->operator, ['IS', 'IS NOT'], true)) {
            throw new InvalidArgumentException(\sprintf('Operator "%s" is forbidden for a null value; use IS or IS NOT.', $condition->operator));
        }

        return $this->left($condition, $alias).' '.$condition->operator.' NULL';
    }

    private function left(Condition $condition, string $alias): string
    {
        return ($condition->keyPrefix ?? $alias.'.').$condition->field;
    }

    /** The unvalidated path: whatever the caller wrote, as it wrote it. */
    private function raw(QueryBuilder $queryBuilder, Condition $condition, string $alias, string $parameter): string
    {
        $left = $this->left($condition, $alias);

        $queryBuilder->setParameter($parameter, $condition->value);

        return 'IN' === $condition->operator
            ? \sprintf('%s IN (:%s)', $left, $parameter)
            : \sprintf('%s %s :%s', $left, $condition->operator, $parameter);
    }
}
