<?php

namespace Pushword\Newsletter\Content;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pushword\Core\Entity\Page;
use Pushword\Core\Query\PageFieldRegistry;
use Pushword\Core\Query\QueryCompiler;
use Pushword\Newsletter\Entity\ContentTrigger;
use Pushword\Newsletter\Entity\ContentTriggerLog;
use Pushword\Newsletter\Segment\SegmentException;

/**
 * Compiles a {@see PageCriteria} rule into a Page query.
 *
 * Every query it builds is scoped to the trigger's hosts, to pages published
 * after its `triggerFrom`, and to pages it has not already handled — the three
 * guards that make a trigger safe to switch on over an existing site.
 *
 * The rule itself is compiled by the same {@see QueryCompiler} a `pages_list`
 * search goes through, against the same {@see PageFieldRegistry}, so `ancestor`
 * or `tag` cannot come to mean one thing in a listing and another in a trigger.
 */
final readonly class PageMatcher
{
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

        $rule = PageCriteria::normalize($trigger->getPageWhen());

        // The three guards above are ANDed with the whole rule, never a disjunct
        // of it: an `any` widens which pages match, never past them.
        if (null !== $rule) {
            new QueryCompiler(new PageFieldRegistry($this->entityManager))->apply($queryBuilder, $rule);
        }

        return $queryBuilder;
    }
}
