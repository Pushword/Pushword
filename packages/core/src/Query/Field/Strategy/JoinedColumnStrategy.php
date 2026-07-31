<?php

namespace Pushword\Core\Query\Field\Strategy;

use Doctrine\ORM\Query\Expr\Join;
use Pushword\Core\Query\Field\FieldCompilation;
use Pushword\Core\Query\Field\FieldStrategy;

/**
 * A column read through a relation — the parent page's slug, which is what an
 * editor knows and what survives a re-parenting.
 *
 * The join is declared on demand, because the queries that need this field do
 * not all start from the same place: a page list joins the parent to render it,
 * a content trigger has no reason to. Absence is a known state here, so a page
 * with no parent belongs on the `!=` side.
 */
final readonly class JoinedColumnStrategy implements FieldStrategy
{
    public function __construct(
        private string $relation,
        private string $alias,
        private string $column,
    ) {
    }

    public function operators(): array
    {
        return ['=', '!='];
    }

    public function compile(FieldCompilation $compilation): string
    {
        $this->declareJoin($compilation);

        $column = $compilation->column($this->column, $this->alias);

        $expression = '=' === $compilation->operator
            ? \sprintf('%s = :%s', $column, $compilation->parameter)
            : \sprintf('(%s IS NULL OR %s != :%s)', $column, $column, $compilation->parameter);

        return $compilation->bind($expression, $compilation->value);
    }

    private function declareJoin(FieldCompilation $compilation): void
    {
        /** @var array<string, array<Join>> $declared */
        $declared = $compilation->queryBuilder->getDQLPart('join');

        foreach ($declared as $joins) {
            foreach ($joins as $join) {
                if ($join->getAlias() === $this->alias) {
                    return;
                }
            }
        }

        $compilation->queryBuilder->leftJoin($compilation->alias.'.'.$this->relation, $this->alias);
    }
}
