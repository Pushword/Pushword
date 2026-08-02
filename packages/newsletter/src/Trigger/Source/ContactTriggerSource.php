<?php

namespace Pushword\Newsletter\Trigger\Source;

use DateTimeImmutable;
use Doctrine\ORM\QueryBuilder;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Repository\ContactRepository;
use Pushword\Newsletter\Repository\TriggerLogRepository;
use Pushword\Newsletter\Segment\SegmentCriteria;
use Pushword\Newsletter\Segment\SegmentResolver;
use Pushword\Newsletter\Trigger\TriggerOccurrence;
use Pushword\Newsletter\Trigger\TriggerSource;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Watches the audience itself: a subscribed contact comes to match the rule.
 *
 * The drip every mailing tool opens with — "two mails after subscription" is an
 * empty rule and two steps. Each contact is its own subject, so it triggers the
 * automation once and the sequence is addressed to them.
 */
#[AutoconfigureTag('pushword.newsletter.trigger_source')]
final readonly class ContactTriggerSource implements TriggerSource
{
    public const string NAME = 'contact';

    public function __construct(
        private SegmentResolver $segmentResolver,
        private TriggerLogRepository $logRepository,
        private ContactRepository $contactRepository,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function criteria(): string
    {
        return SegmentCriteria::class;
    }

    public function occurrences(Automation $automation, DateTimeImmutable $now, ?int $limit = null): array
    {
        $queryBuilder = $this->queryBuilder($automation, $now);

        if (null === $queryBuilder) {
            return [];
        }

        $queryBuilder->orderBy('c.createdAt', 'ASC')->addOrderBy('c.id', 'ASC');

        if (null !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        /** @var list<Contact> $contacts */
        $contacts = $queryBuilder->getQuery()->getResult();

        $occurrences = [];

        foreach ($contacts as $contact) {
            $id = $contact->id;

            if (null === $id) {
                continue;
            }

            $occurrences[] = new TriggerOccurrence(
                subjectId: $id,
                // The registration, not the tick: a contact the automation was
                // switched on for yesterday starts their sequence from when they
                // signed up, and the first step's delay counts from there.
                occurredAt: DateTimeImmutable::createFromInterface($contact->getRegisteredAt()),
                placeholders: [
                    'contact.name' => $contact->name,
                    'contact.email' => $contact->email,
                ],
                contact: $contact,
            );
        }

        return $occurrences;
    }

    public function count(Automation $automation, DateTimeImmutable $now): int
    {
        $queryBuilder = $this->queryBuilder($automation, $now);

        if (null === $queryBuilder) {
            return 0;
        }

        return (int) $queryBuilder->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
    }

    /**
     * A contact who unsubscribed during the sequence is dropped by the drip
     * itself, before each step, so what is left to check here is only that they
     * are still there at all.
     */
    public function stillMatches(int $subjectId): bool
    {
        return $this->contactRepository->find($subjectId) instanceof Contact;
    }

    /** Null when the automation has no audience to read contacts from yet. */
    private function queryBuilder(Automation $automation, DateTimeImmutable $now): ?QueryBuilder
    {
        $audience = $automation->audience;

        if (null === $audience) {
            return null;
        }

        // The segment resolver already scopes to the audience and to subscribed
        // contacts, and compiles the rule; the three guards below are what makes
        // it a trigger rather than a segment.
        return $this->segmentResolver->queryBuilder($audience, $automation->triggerWhen)
            ->andWhere('c.createdAt >= :activeFrom')
            ->andWhere('c.createdAt <= :now')
            ->andWhere('c.id NOT IN ('.$this->logRepository->handledSubjectsDql().')')
            ->setParameter('activeFrom', $automation->activeFrom)
            ->setParameter('now', $now)
            ->setParameter('automation', $automation);
    }
}
