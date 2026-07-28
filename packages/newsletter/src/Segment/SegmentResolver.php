<?php

namespace Pushword\Newsletter\Segment;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Enum\ContactStatus;

/**
 * Compiles a {@see SegmentCriteria} list into a Contact query.
 *
 * Every query it builds is scoped to one audience and to subscribed contacts:
 * an unsubscribed or bounced address cannot be reached by any criteria that can
 * be written, which is the property the whole sending side relies on.
 */
final readonly class SegmentResolver
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param array<mixed> $criteria
     *
     * @throws SegmentException
     */
    public function count(Audience $audience, array $criteria): int
    {
        return (int) $this->queryBuilder($audience, $criteria)
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param array<mixed> $criteria
     *
     * @return list<Contact>
     *
     * @throws SegmentException
     */
    public function contacts(Audience $audience, array $criteria, ?int $limit = null): array
    {
        $queryBuilder = $this->queryBuilder($audience, $criteria)->orderBy('c.id', 'ASC');

        if (null !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        /** @var list<Contact> $contacts */
        $contacts = $queryBuilder->getQuery()->getResult();

        return $contacts;
    }

    /**
     * Does this single contact belong to the segment? Runs the same compiled
     * query narrowed to one id, so a stop condition can never disagree with the
     * enrollment rule that used the same criteria.
     *
     * @param array<mixed> $criteria
     *
     * @throws SegmentException
     */
    public function matches(Contact $contact, array $criteria): bool
    {
        $count = (int) $this->queryBuilder($contact->getAudience(), $criteria)
            ->select('COUNT(c.id)')
            ->andWhere('c.id = :contactId')
            ->setParameter('contactId', $contact->id)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * @param array<mixed> $criteria
     *
     * @throws SegmentException
     */
    public function queryBuilder(Audience $audience, array $criteria): QueryBuilder
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Contact::class, 'c')
            ->andWhere('c.audience = :audience')
            ->andWhere('c.status = :subscribed')
            ->setParameter('audience', $audience)
            ->setParameter('subscribed', ContactStatus::Subscribed->value);

        $now = new DateTimeImmutable();

        foreach (SegmentCriteria::normalize($criteria) as $index => $condition) {
            $this->applyCondition($queryBuilder, $condition, $index, $now);
        }

        return $queryBuilder;
    }

    /** @param array{field: string, op: string, value: string} $condition */
    private function applyCondition(QueryBuilder $queryBuilder, array $condition, int $index, DateTimeImmutable $now): void
    {
        $parameter = 'seg'.$index;
        ['field' => $field, 'op' => $op, 'value' => $value] = $condition;

        if (SegmentCriteria::isProperty($field)) {
            $this->applyProperty($queryBuilder, $field, $op, $value, $parameter);

            return;
        }

        match ($field) {
            'tag' => $this->applyTag($queryBuilder, $op, $value, $parameter),
            'locale' => $queryBuilder
                ->andWhere(\sprintf('c.locale %s :%s', $op, $parameter))
                ->setParameter($parameter, $value),
            default => $queryBuilder
                ->andWhere(\sprintf('c.%s %s :%s', $field, 'olderThan' === $op ? '<=' : '>=', $parameter))
                ->setParameter($parameter, SegmentCriteria::threshold($value, $now)),
        };
    }

    /**
     * Tags live in a JSON array column; matching on the quoted form (`"AmTrek"`)
     * keeps a shorter tag from matching a longer one that starts with it.
     */
    private function applyTag(QueryBuilder $queryBuilder, string $op, string $value, string $parameter): void
    {
        $queryBuilder
            ->andWhere(\sprintf('c.tags %s :%s', 'has' === $op ? 'LIKE' : 'NOT LIKE', $parameter))
            ->setParameter($parameter, '%"'.$value.'"%');
    }

    private function applyProperty(QueryBuilder $queryBuilder, string $field, string $op, string $value, string $parameter): void
    {
        $extract = \sprintf("JSON_SCALAR(c.customProperties, '%s')", SegmentCriteria::propertyPath($field));

        match ($op) {
            'isSet' => $queryBuilder->andWhere($extract.' IS NOT NULL'),
            'isNotSet' => $queryBuilder->andWhere($extract.' IS NULL'),
            // A missing property is not "different from tmb" — it is unknown; an
            // explicit IS NOT NULL keeps != from silently widening the segment.
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
