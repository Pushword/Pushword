<?php

namespace Pushword\Core\Repository;

use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use Exception;

/**
 * Eg:
 * ['title', 'LIKE' '%this%'] => works
 * [['title', 'LIKE' '%this%']] => works
 * [['title', 'LIKE' '%this%'], 'OR', ['title', 'LIKE' '%that%']] => works
 * [[['title', 'LIKE' '%this%'], ['title', 'LIKE' '%this%']], 'OR', ['title', 'LIKE' '%that%']] => works
 */
class FilterWhereParser
{
    /**
     * @param array<mixed> $where
     */
    public function __construct(
        private readonly QueryBuilder $queryBuilder,
        private array $where
    ) {
    }

    public function parseAndAdd(): QueryBuilder
    {
        if ([] === $this->where) {
            return $this->queryBuilder;
        }

        // Normalize array [']
        if (! isset($this->where[0]) || ! \is_array($this->where[0])) { // eg : ['key' => 'test'...] or ['test', ...]
            $this->where = [$this->where];
        }

        return $this->queryBuilder->andWhere($this->retrieveFrom($this->where));
    }

    /**
     * @param array<mixed> $where
     */
    private function containsSubQuery(array $where): bool
    {
        return \is_array(array_values($where)[0] ?? throw new Exception());
    }

    /**
     * @param array<mixed> $where
     */
    private function retrieveFrom(array $where): Andx|Orx
    {
        $compose = \in_array('OR', $where, true) ? $this->queryBuilder->expr()->orX() : $this->queryBuilder->expr()->andX();
        foreach ($where as $singleWhere) {
            if ('OR' === $singleWhere) {
                continue;
            }

            if (! \is_array($singleWhere)) {
                throw new Exception('malformated where params');
            }

            if ($this->containsSubQuery($singleWhere)) {
                $compose->add($this->retrieveFrom($singleWhere));

                continue;
            }

            /** @psalm-suppress MixedArgumentTypeCoercion */
            $compose->add($this->retrieveExpressionFrom($singleWhere));
        }

        return $compose;
    }

    /**
     * @param array{key_prefix: string, key: string, operator: string, value: string}|array{0: string, 1:string, 2: string, 4:string}|array{} $whereRow
     */
    private function retrieveExpressionFrom(array $whereRow): string
    {
        $paramKey = 'm'.md5('a'.random_int(0, mt_getrandmax()));

        $prefix = $whereRow['key_prefix'] ?? $whereRow[4] ?? 'p.';
        $key = $whereRow['key'] ?? $whereRow[0] ?? throw new Exception('key was forgotten');
        $operator = $whereRow['operator'] ?? $whereRow[1] ?? throw new Exception('operator was forgotten');
        $sqlValue = 'IN' === $operator ? '( :'.$paramKey.')' : ' :'.$paramKey;
        $value = $whereRow['value'] ?? $whereRow[2] ?? null;

        if (null === $value) {
            if (! \in_array($operator, ['IS', 'IS NOT'], true)) {
                throw new Exception('operator `'.$operator.'` forbidden for null value');
            }

            return $prefix.$key.' '.$operator.' NULL';
        }

        $this->queryBuilder->setParameter($paramKey, $value);

        return $prefix.$key.' '.$operator.' '.$sqlValue;
    }
}
