<?php

namespace Pushword\Newsletter\Trigger\Source;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pushword\Core\Entity\Page;
use Pushword\Core\PropertySchema\PagePropertySchemaRegistry;
use Pushword\Core\Query\PageFieldRegistry;
use Pushword\Core\Query\QueryCompiler;
use Pushword\Core\Repository\PageRepository;
use Pushword\Newsletter\Content\PageCriteria;
use Pushword\Newsletter\Content\PagePlaceholders;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Repository\TriggerLogRepository;
use Pushword\Newsletter\Trigger\TriggerOccurrence;
use Pushword\Newsletter\Trigger\TriggerSource;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Watches the site: a page is published, and the audience hears about it.
 *
 * An occurrence names no contact, so its steps go out as broadcasts — one
 * scheduled campaign per step per page, addressed to whoever the automation's
 * `recipientWhen` selects at the moment each one is armed.
 *
 * The rule is compiled by the same {@see QueryCompiler} a `pages_list` search
 * goes through, against the same {@see PageFieldRegistry}, so `ancestor` or
 * `tag` cannot come to mean one thing in a listing and another in a trigger.
 */
#[AutoconfigureTag('pushword.newsletter.trigger_source')]
final readonly class PageTriggerSource implements TriggerSource
{
    public const string NAME = 'page';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PageRepository $pageRepository,
        private TriggerLogRepository $logRepository,
        private PagePropertySchemaRegistry $schemaRegistry,
        private PagePlaceholders $placeholders,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function criteria(): string
    {
        return PageCriteria::class;
    }

    /**
     * @return list<TriggerOccurrence>
     */
    public function occurrences(Automation $automation, DateTimeImmutable $now, ?int $limit = null): array
    {
        $queryBuilder = $this->queryBuilder($automation, $now)->orderBy('p.publishedAt', 'ASC');

        if (null !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        /** @var list<Page> $pages */
        $pages = $queryBuilder->getQuery()->getResult();

        $occurrences = [];

        foreach ($pages as $page) {
            $id = $page->id;

            if (null === $id) {
                continue;
            }

            $occurrences[] = new TriggerOccurrence(
                subjectId: $id,
                occurredAt: DateTimeImmutable::createFromInterface($page->publishedAt ?? $now),
                placeholders: $this->placeholders->map($page),
                slug: $page->slug,
            );
        }

        return $occurrences;
    }

    public function count(Automation $automation, DateTimeImmutable $now): int
    {
        return (int) $this->queryBuilder($automation, $now)
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** A page unpublished, or deleted, during the delay is no longer worth a mail. */
    public function stillMatches(int $subjectId): bool
    {
        $page = $this->pageRepository->find($subjectId);

        return $page instanceof Page && $page->isPublished();
    }

    /**
     * Scoped to the automation's hosts, to pages published after its
     * `activeFrom`, and to pages it has not already handled — the three guards
     * that make it safe to switch on over an existing site.
     */
    private function queryBuilder(Automation $automation, DateTimeImmutable $now): QueryBuilder
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Page::class, 'p')
            ->andWhere('p.publishedAt > :activeFrom')
            ->andWhere('p.publishedAt <= :now')
            ->andWhere('p.id NOT IN ('.$this->logRepository->handledSubjectsDql().')')
            ->setParameter('activeFrom', $automation->activeFrom)
            ->setParameter('now', $now)
            ->setParameter('automation', $automation);

        $hosts = $automation->hosts;
        if ([] !== $hosts) {
            $queryBuilder->andWhere('p.host IN (:hosts)')->setParameter('hosts', $hosts);
        }

        $rule = PageCriteria::normalize($automation->triggerWhen);

        // The three guards above are ANDed with the whole rule, never a disjunct
        // of it: an `any` widens which pages match, never past them.
        if (null !== $rule) {
            new QueryCompiler(new PageFieldRegistry($this->entityManager, $this->schemaRegistry))->apply($queryBuilder, $rule);
        }

        return $queryBuilder;
    }
}
