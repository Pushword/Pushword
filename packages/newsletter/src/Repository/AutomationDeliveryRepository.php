<?php

namespace Pushword\Newsletter\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Newsletter\Entity\AutomationDelivery;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Enum\RecipientState;

/**
 * @extends ServiceEntityRepository<AutomationDelivery>
 */
class AutomationDeliveryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AutomationDelivery::class);
    }

    /**
     * The last drip step this contact actually received — the other half of the
     * anchor a later unsubscribe or bounce is attributed to.
     */
    public function findLastSentFor(Contact $contact): ?AutomationDelivery
    {
        $delivery = $this->createQueryBuilder('d')
            ->andWhere('d.contact = :contact')
            ->andWhere('d.state = :sent')
            ->setParameter('contact', $contact)
            ->setParameter('sent', RecipientState::Sent->value)
            ->orderBy('d.attemptedAt', 'DESC')
            ->addOrderBy('d.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $delivery instanceof AutomationDelivery ? $delivery : null;
    }
}
