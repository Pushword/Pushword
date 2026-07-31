<?php

namespace Pushword\Core\Repository;

use Doctrine\ORM\QueryBuilder;
use Pushword\Core\Query\ArrayCriteriaReader;
use Pushword\Core\Query\PageFieldRegistry;
use Pushword\Core\Query\QueryCompiler;

/**
 * Eg:
 * ['title', 'LIKE' '%this%'] => works
 * [['title', 'LIKE' '%this%']] => works
 * [['title', 'LIKE' '%this%'], 'OR', ['title', 'LIKE' '%that%']] => works
 * [[['title', 'LIKE' '%this%'], ['title', 'LIKE' '%this%']], 'OR', ['title', 'LIKE' '%that%']] => works
 *
 * @deprecated read the array with {@see ArrayCriteriaReader} and compile the
 *             tree with {@see QueryCompiler}; this only keeps the old entry
 *             point working
 */
class FilterWhereParser
{
    /**
     * @param array<mixed> $where
     */
    public function __construct(
        private readonly QueryBuilder $queryBuilder,
        private readonly array $where
    ) {
    }

    public function parseAndAdd(): QueryBuilder
    {
        $criteria = new ArrayCriteriaReader()->read($this->where);

        if (null === $criteria) {
            return $this->queryBuilder;
        }

        $entityManager = $this->queryBuilder->getEntityManager();

        return new QueryCompiler(new PageFieldRegistry($entityManager))
            ->apply($this->queryBuilder, $criteria, $this->rootAlias());
    }

    private function rootAlias(): string
    {
        $aliases = $this->queryBuilder->getRootAliases();

        return $aliases[0] ?? 'p';
    }
}
