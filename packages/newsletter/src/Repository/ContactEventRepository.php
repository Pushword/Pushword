<?php

namespace Pushword\Newsletter\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Entity\ContactEvent;

/**
 * @extends ServiceEntityRepository<ContactEvent>
 */
class ContactEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactEvent::class);
    }

    /**
     * The whole history of one subscription, oldest first — the order it has to
     * be read in to mean anything.
     *
     * The id breaks ties: an opt-in with the confirmation clicked in the same
     * second is two rows sharing a date, and the insertion order is the only
     * thing that still tells them apart.
     *
     * @return list<ContactEvent>
     */
    public function findFor(Contact $contact): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.contact = :contact')
            ->setParameter('contact', $contact)
            ->orderBy('e.occurredAt', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
