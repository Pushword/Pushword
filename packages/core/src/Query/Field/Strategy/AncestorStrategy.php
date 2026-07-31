<?php

namespace Pushword\Core\Query\Field\Strategy;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Query\Field\FieldCompilation;
use Pushword\Core\Query\Field\FieldStrategy;

/**
 * A whole section in one condition: the value is the slug of a page the result
 * sits under, however deep.
 *
 * `parentPage` names a single rubric, so a blog split in three needs three
 * conditions; this names the blog. It also keeps covering the rubric added next
 * month, which an enumeration does not.
 */
final readonly class AncestorStrategy implements FieldStrategy
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function operators(): array
    {
        return ['=', '!='];
    }

    public function compile(FieldCompilation $compilation): string
    {
        $section = $this->sectionIds($compilation->stringValue());

        // Nothing sits under a page that does not exist, and everything sits
        // outside of it — as a constant, so that an OR group reads it as the
        // false (or true) member it is rather than losing the condition.
        if ([] === $section) {
            return '=' === $compilation->operator ? '1 = 0' : '1 = 1';
        }

        $column = $compilation->column('parentPage');

        return $compilation->bind(
            '=' === $compilation->operator
                ? \sprintf('%s IN (:%s)', $column, $compilation->parameter)
                : \sprintf('(%s IS NULL OR %s NOT IN (:%s))', $column, $column, $compilation->parameter),
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
}
