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

        $rule = PageCriteria::normalize($trigger->getPageWhen());
        $conditions = [];

        foreach ($rule['conditions'] as $index => $condition) {
            $conditions[] = $this->condition($queryBuilder, $condition, $index);
        }

        // The three guards above are ANDed with the whole group, never a disjunct
        // of it: `any` widens which pages match, never past them.
        if ([] !== $conditions) {
            $queryBuilder->andWhere($rule['any']
                ? $queryBuilder->expr()->orX(...$conditions)
                : $queryBuilder->expr()->andX(...$conditions));
        }

        return $queryBuilder;
    }

    /** @param array{field: string, op: string, value: string} $condition */
    private function condition(QueryBuilder $queryBuilder, array $condition, int $index): string
    {
        $parameter = 'page'.$index;
        ['field' => $field, 'op' => $op, 'value' => $value] = $condition;

        if (PageCriteria::isProperty($field)) {
            return $this->property($queryBuilder, $field, $op, $value, $parameter);
        }

        return match ($field) {
            'slug' => $this->slug($queryBuilder, $op, $value, $parameter),
            'tag' => $this->tag($queryBuilder, $op, $value, $parameter),
            'parentPage' => $this->parent($queryBuilder, $op, $value, $parameter),
            'ancestor' => $this->ancestor($queryBuilder, $op, $value, $parameter),
            default => $this->template($queryBuilder, $op, $value, $parameter),
        };
    }

    private function slug(QueryBuilder $queryBuilder, string $op, string $value, string $parameter): string
    {
        return $this->parameterized(
            $queryBuilder,
            \sprintf("p.slug %s :%s ESCAPE '%s'", 'startsWith' === $op ? 'LIKE' : 'NOT LIKE', $parameter, self::LIKE_ESCAPE),
            $parameter,
            $this->escapeLike($value).'%',
        );
    }

    private function parameterized(QueryBuilder $queryBuilder, string $expression, string $parameter, mixed $value): string
    {
        $queryBuilder->setParameter($parameter, $value);

        return $expression;
    }

    /** Every special character gets the escape prefix; nothing else is touched. */
    private function escapeLike(string $value): string
    {
        $special = [self::LIKE_ESCAPE, '%', '_'];

        return str_replace($special, array_map(static fn (string $c): string => self::LIKE_ESCAPE.$c, $special), $value);
    }

    /**
     * The other axis a `pages_list` search groups pages by, matched the way
     * {@see \Pushword\Newsletter\Segment\SegmentResolver} matches a contact's:
     * tags live in a JSON array column, and the quoted form keeps a shorter tag
     * from matching a longer one that starts with it.
     */
    private function tag(QueryBuilder $queryBuilder, string $op, string $value, string $parameter): string
    {
        return $this->parameterized(
            $queryBuilder,
            \sprintf("p.tags %s :%s ESCAPE '%s'", 'has' === $op ? 'LIKE' : 'NOT LIKE', $parameter, self::LIKE_ESCAPE),
            $parameter,
            '%"'.$this->escapeLike($value).'"%',
        );
    }

    /** The value is the parent's slug: what an editor knows, and what survives a re-parenting. */
    private function parent(QueryBuilder $queryBuilder, string $op, string $value, string $parameter): string
    {
        if ([] === $queryBuilder->getDQLPart('join')) {
            $queryBuilder->leftJoin('p.parentPage', 'parent');
        }

        return $this->parameterized(
            $queryBuilder,
            '=' === $op
                ? \sprintf('parent.slug = :%s', $parameter)
                : \sprintf('(parent.slug IS NULL OR parent.slug != :%s)', $parameter),
            $parameter,
            $value,
        );
    }

    /**
     * A whole section in one condition: the value is the slug of a page the
     * article sits under, however deep. `parentPage` names a single rubric, so
     * a blog split in three needs three conditions; this names the blog.
     */
    private function ancestor(QueryBuilder $queryBuilder, string $op, string $value, string $parameter): string
    {
        $section = $this->sectionIds($value);

        // Nothing sits under a page that does not exist, and everything sits
        // outside of it — as a constant, so that an `any` group reads it as the
        // false (or true) member it is rather than losing the condition.
        if ([] === $section) {
            return '=' === $op ? '1 = 0' : '1 = 1';
        }

        return $this->parameterized(
            $queryBuilder,
            '=' === $op
                ? \sprintf('p.parentPage IN (:%s)', $parameter)
                : \sprintf('(p.parentPage IS NULL OR p.parentPage NOT IN (:%s))', $parameter),
            $parameter,
            $section,
        );
    }

    /**
     * The page named plus everything below it: the parents a page of that
     * section can have. Walked one level at a time — Doctrine has no recursive
     * query, and a page tree is a few rubrics deep. A page has a single parent
     * and cannot be its own ancestor, so no level ever revisits an earlier one.
     *
     * @return list<int>
     */
    private function sectionIds(string $slug): array
    {
        $section = $this->pageIds('p.slug = :value', $slug);
        $level = $section;

        while ([] !== $level) {
            $level = $this->pageIds('p.parentPage IN (:value)', $level);
            $section = [...$section, ...$level];
        }

        return $section;
    }

    /**
     * @param string|list<int> $value
     *
     * @return list<int>
     */
    private function pageIds(string $where, string|array $value): array
    {
        /** @var list<int|string> $ids */
        $ids = $this->entityManager->createQueryBuilder()
            ->select('p.id')
            ->from(Page::class, 'p')
            ->andWhere($where)
            ->setParameter('value', $value)
            ->getQuery()
            ->getSingleColumnResult();

        return array_map(static fn (int|string $id): int => (int) $id, $ids);
    }

    /**
     * An absent template is the site's default one — a known value, and
     * genuinely not the one being excluded. Unlike a missing property, which is
     * unknown, NULL therefore belongs on the `!=` side.
     */
    private function template(QueryBuilder $queryBuilder, string $op, string $value, string $parameter): string
    {
        return $this->parameterized(
            $queryBuilder,
            '=' === $op
                ? \sprintf('p.template = :%s', $parameter)
                : \sprintf('(p.template IS NULL OR p.template != :%s)', $parameter),
            $parameter,
            $value,
        );
    }

    private function property(QueryBuilder $queryBuilder, string $field, string $op, string $value, string $parameter): string
    {
        $extract = \sprintf("JSON_SCALAR(p.customProperties, '%s')", PageCriteria::propertyPath($field));

        return match ($op) {
            'isSet' => $extract.' IS NOT NULL',
            'isNotSet' => $extract.' IS NULL',
            // A missing property is not "different from x" — it is unknown; an
            // explicit IS NOT NULL keeps != from silently widening the match.
            '!=' => $this->parameterized($queryBuilder, \sprintf('(%s IS NOT NULL AND %s != :%s)', $extract, $extract, $parameter), $parameter, $value),
            default => $this->parameterized($queryBuilder, \sprintf('%s = :%s', $extract, $parameter), $parameter, $value),
        };
    }
}
