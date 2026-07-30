<?php

namespace Pushword\Newsletter\Content;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pushword\Core\Entity\Page;
use Pushword\Newsletter\Entity\ContentTrigger;
use Pushword\Newsletter\Entity\ContentTriggerLog;
use Pushword\Newsletter\Segment\SegmentException;

/**
 * Compiles a {@see PageCriteria} list into a Page query.
 *
 * Every query it builds is scoped to the trigger's hosts, to pages published
 * after its `triggerFrom`, and to pages it has not already handled — the three
 * guards that make a trigger safe to switch on over an existing site.
 */
final readonly class PageMatcher
{
    /**
     * `_` is both a LIKE wildcard and a legal slug character, so a prefix has to
     * be escaped. Not with a backslash: SQLite gives LIKE no escape character at
     * all unless one is named, where MySQL takes the backslash for granted — the
     * same pattern would then match nothing on one and work on the other.
     */
    private const string LIKE_ESCAPE = '!';

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return list<Page>
     *
     * @throws SegmentException
     */
    public function pages(ContentTrigger $trigger, DateTimeImmutable $now, ?int $limit = null): array
    {
        $queryBuilder = $this->queryBuilder($trigger, $now)->orderBy('p.publishedAt', 'ASC');

        if (null !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        /** @var list<Page> $pages */
        $pages = $queryBuilder->getQuery()->getResult();

        return $pages;
    }

    /** @throws SegmentException */
    public function count(ContentTrigger $trigger, DateTimeImmutable $now): int
    {
        return (int) $this->queryBuilder($trigger, $now)
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @throws SegmentException */
    private function queryBuilder(ContentTrigger $trigger, DateTimeImmutable $now): QueryBuilder
    {
        $alreadyHandled = $this->entityManager->createQueryBuilder()
            ->select('handled.pageId')
            ->from(ContentTriggerLog::class, 'handled')
            ->andWhere('handled.trigger = :trigger')
            ->getDQL();

        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Page::class, 'p')
            ->andWhere('p.publishedAt > :triggerFrom')
            ->andWhere('p.publishedAt <= :now')
            ->andWhere('p.id NOT IN ('.$alreadyHandled.')')
            ->setParameter('triggerFrom', $trigger->getTriggerFrom())
            ->setParameter('now', $now)
            ->setParameter('trigger', $trigger);

        $hosts = $trigger->getHosts();
        if ([] !== $hosts) {
            $queryBuilder->andWhere('p.host IN (:hosts)')->setParameter('hosts', $hosts);
        }

        foreach (PageCriteria::normalize($trigger->getPageWhen()) as $index => $condition) {
            $this->applyCondition($queryBuilder, $condition, $index);
        }

        return $queryBuilder;
    }

    /** @param array{field: string, op: string, value: string} $condition */
    private function applyCondition(QueryBuilder $queryBuilder, array $condition, int $index): void
    {
        $parameter = 'page'.$index;
        ['field' => $field, 'op' => $op, 'value' => $value] = $condition;

        if (PageCriteria::isProperty($field)) {
            $this->applyProperty($queryBuilder, $field, $op, $value, $parameter);

            return;
        }

        match ($field) {
            'slug' => $this->applySlug($queryBuilder, $op, $value, $parameter),
            'parentPage' => $this->applyParent($queryBuilder, $op, $value, $parameter),
            default => $this->applyTemplate($queryBuilder, $op, $value, $parameter),
        };
    }

    private function applySlug(QueryBuilder $queryBuilder, string $op, string $value, string $parameter): void
    {
        $queryBuilder
            ->andWhere(\sprintf("p.slug %s :%s ESCAPE '%s'", 'startsWith' === $op ? 'LIKE' : 'NOT LIKE', $parameter, self::LIKE_ESCAPE))
            ->setParameter($parameter, $this->escapeLike($value).'%');
    }

    /** Every special character gets the escape prefix; nothing else is touched. */
    private function escapeLike(string $value): string
    {
        $special = [self::LIKE_ESCAPE, '%', '_'];

        return str_replace($special, array_map(static fn (string $c): string => self::LIKE_ESCAPE.$c, $special), $value);
    }

    /** The value is the parent's slug: what an editor knows, and what survives a re-parenting. */
    private function applyParent(QueryBuilder $queryBuilder, string $op, string $value, string $parameter): void
    {
        if ([] === $queryBuilder->getDQLPart('join')) {
            $queryBuilder->leftJoin('p.parentPage', 'parent');
        }

        $queryBuilder
            ->andWhere('=' === $op
                ? \sprintf('parent.slug = :%s', $parameter)
                : \sprintf('(parent.slug IS NULL OR parent.slug != :%s)', $parameter))
            ->setParameter($parameter, $value);
    }

    /**
     * An absent template is the site's default one — a known value, and
     * genuinely not the one being excluded. Unlike a missing property, which is
     * unknown, NULL therefore belongs on the `!=` side.
     */
    private function applyTemplate(QueryBuilder $queryBuilder, string $op, string $value, string $parameter): void
    {
        $queryBuilder
            ->andWhere('=' === $op
                ? \sprintf('p.template = :%s', $parameter)
                : \sprintf('(p.template IS NULL OR p.template != :%s)', $parameter))
            ->setParameter($parameter, $value);
    }

    private function applyProperty(QueryBuilder $queryBuilder, string $field, string $op, string $value, string $parameter): void
    {
        $extract = \sprintf("JSON_SCALAR(p.customProperties, '%s')", PageCriteria::propertyPath($field));

        match ($op) {
            'isSet' => $queryBuilder->andWhere($extract.' IS NOT NULL'),
            'isNotSet' => $queryBuilder->andWhere($extract.' IS NULL'),
            // A missing property is not "different from x" — it is unknown; an
            // explicit IS NOT NULL keeps != from silently widening the match.
            '!=' => $queryBuilder
                ->andWhere($extract.' IS NOT NULL')
                ->andWhere(\sprintf('%s != :%s', $extract, $parameter))
                ->setParameter($parameter, $value),
            default => $queryBuilder
                ->andWhere(\sprintf('%s = :%s', $extract, $parameter))
                ->setParameter($parameter, $value),
        };
    }
}
