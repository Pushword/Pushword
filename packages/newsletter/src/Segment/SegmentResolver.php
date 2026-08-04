<?php

namespace Pushword\Newsletter\Segment;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pushword\Core\Query\QueryCompiler;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Enum\ContactStatus;
use Pushword\Newsletter\Query\ContactFieldRegistry;

/**
 * Compiles a {@see SegmentCriteria} rule into a Contact query.
 *
 * Every query it builds is scoped to one audience and to subscribed contacts:
 * an unsubscribed or bounced address cannot be reached by any criteria that can
 * be written, which is the property the whole sending side relies on.
 *
 * The `mailable` variants add the second half of that property — an address to
 * send to — and are what every surface that *sends* or predicts a send goes
 * through. The plain ones stay unfiltered, because a contact the site can only
 * phone is still to be listed, segmented and exported; they are simply never a
 * recipient.
 *
 * What each field means is {@see ContactFieldRegistry}'s business, and walking
 * the rule is {@see QueryCompiler}'s, so what is left here is exactly those
 * guards.
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
     * How many the segment would actually reach — what a preview before Send has
     * to say, and what a campaign's `estimatedRecipients` means.
     *
     * @param array<mixed> $criteria
     *
     * @throws SegmentException
     */
    public function countMailable(Audience $audience, array $criteria): int
    {
        return (int) $this->mailableQueryBuilder($audience, $criteria)
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
    public function mailableContacts(Audience $audience, array $criteria, ?int $limit = null): array
    {
        return $this->fetch($this->mailableQueryBuilder($audience, $criteria), $limit);
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
        return $this->fetch($this->queryBuilder($audience, $criteria), $limit);
    }

    /** @return list<Contact> */
    private function fetch(QueryBuilder $queryBuilder, ?int $limit): array
    {
        $queryBuilder->orderBy('c.id', 'ASC');

        if (null !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        /** @var list<Contact> $contacts */
        $contacts = $queryBuilder->getQuery()->getResult();

        return $contacts;
    }

    /**
     * The same query, narrowed to the contacts a mail can reach.
     *
     * @param array<mixed> $criteria
     *
     * @throws SegmentException
     */
    public function mailableQueryBuilder(Audience $audience, array $criteria): QueryBuilder
    {
        return $this->queryBuilder($audience, $criteria)->andWhere('c.email IS NOT NULL');
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
        $count = (int) $this->queryBuilder($contact->audience, $criteria)
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

        $rule = SegmentCriteria::normalize($criteria);

        // The audience and the subscribed status are ANDed with the whole rule,
        // never a disjunct of it: an `any` widens who is reached, never past them.
        if (null !== $rule) {
            new QueryCompiler(new ContactFieldRegistry(new DateTimeImmutable()))->apply($queryBuilder, $rule, 'c');
        }

        return $queryBuilder;
    }
}
